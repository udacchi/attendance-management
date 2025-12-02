<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Carbon\Carbon;

class AttendanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() || auth()->check();
    }

    public function rules(): array
    {
        return [
            'date'      => ['required', 'date'],
            'clock_in'  => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],

            // 個別の休憩フォーマットエラーは出さない（afterでまとめる）
            'breaks'         => ['sometimes', 'array'],
            'breaks.*.start' => ['nullable'],
            'breaks.*.end'   => ['nullable'],

            'note' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required'         => '日付が取得できませんでした',
            'date.date'             => '日付の形式が不正です',
            'clock_in.date_format'  => '出勤時間が不適切な値です',
            'clock_out.date_format' => '退勤時間が不適切な値です',
            'note.required'         => '備考を記入してください',
        ];
    }

    protected function prepareForValidation(): void
    {
        // 旧形式 break1_*, break2_* を breaks[] に取り込み
        $breaks = (array)$this->input('breaks', []);

        $push = function ($s, $e) use (&$breaks) {
            $s = $s !== null ? trim((string)$s) : null;
            $e = $e !== null ? trim((string)$e) : null;
            if (($s === null || $s === '') && ($e === null || $e === '')) return;
            $breaks[] = ['start' => $s, 'end' => $e];
        };

        $push($this->input('break1_start'), $this->input('break1_end'));
        $push($this->input('break2_start'), $this->input('break2_end'));

        $this->merge([
            'date'   => $this->query('date') ?: $this->input('date'),
            'breaks' => $breaks,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $date = (string)$this->input('date');
            if (!$date) return;

            $tz = config('app.timezone', 'Asia/Tokyo');

            $toDT = function (?string $hm) use ($date, $tz) {
                if (!$hm || strpos($hm, ':') === false) return null;
                try {
                    return Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $hm, $tz)->second(0);
                } catch (\Throwable $e) {
                    return null;
                }
            };

            $in  = $toDT($this->input('clock_in'));
            $out = $toDT($this->input('clock_out'));

            // --- 勤務スパンを決定（跨日対応）---
            $spanIn  = $in;
            $spanOut = $out;

            if ($spanIn && $spanOut) {
                // 退勤が出勤より前なら翌日扱い（跨日）
                if ($spanOut->lt($spanIn)) {
                    $spanOut = $spanOut->copy()->addDay();
                }
                // 同一時刻は不可
                if ($spanOut->equalTo($spanIn)) {
                    $v->errors()->add('clock_pair', '出勤時間もしくは退勤時間が不適切な値です');
                }
            }

            // --- 休憩検証（まとめて breaks_range に集約）---
            $rangeInvalid = false;
            $overOut      = false;
            $breaks       = (array)$this->input('breaks', []);

            // 出勤・退勤がそろっていないのに休憩があるのは不可
            $hasAnyBreakInput = collect($breaks)->contains(function ($row) {
                $s = isset($row['start']) ? trim((string)$row['start']) : '';
                $e = isset($row['end'])   ? trim((string)$row['end'])   : '';
                return $s !== '' || $e !== '';
            });
            if ($hasAnyBreakInput && (!$spanIn || !$spanOut)) {
                $v->errors()->add('breaks_range', '出勤と退勤が無い状態で休憩は登録できません。');
                return;
            }

            $totalBreakSec = 0;
            $spanSec = ($spanIn && $spanOut) ? $spanOut->diffInSeconds($spanIn) : null;

            foreach ($breaks as $i => $row) {
                $sRaw = isset($row['start']) ? trim((string)$row['start']) : '';
                $eRaw = isset($row['end'])   ? trim((string)$row['end'])   : '';

                // 完全空行はスキップ
                if ($sRaw === '' && $eRaw === '') continue;

                $s = $toDT($sRaw);
                $e = $toDT($eRaw);

                // 形式不正 or 片方欠落
                if (!$s || !$e) {
                    $rangeInvalid = true;
                    continue;
                }

                // 休憩自体が跨いだ場合は翌日扱い（基本は非推奨だが安全のため）
                if ($e->lt($s)) $e = $e->copy()->addDay();

                // 勤務スパン外は不可（退勤以降の休憩もここで弾ける）
                if ($spanIn && $spanOut) {
                    if ($s->lt($spanIn) || $e->gt($spanOut) || !$e->gt($s)) {
                        $rangeInvalid = true;
                        if ($e->gt($spanOut)) $overOut = true; // メッセージ分岐用
                        continue;
                    }
                    $totalBreakSec += max(0, $e->diffInSeconds($s));
                }
            }

            // 休憩合計が勤務スパンを超えないこと（勤務外休憩の混入を検知）
            if ($spanSec !== null && $totalBreakSec > $spanSec) {
                $rangeInvalid = true;
                $overOut = true;
            }

            if ($rangeInvalid) {
                $msg = $overOut
                    ? '休憩時間もしくは退勤時間が不適切な値です'
                    : '休憩時間が不適切な値です';
                $v->errors()->add('breaks_range', $msg);
            }
        });
    }


    // 失敗時の戻り先
    protected function getRedirectUrl(): string
    {
        $date = (string)($this->input('date') ?? $this->route('date'));
        /** @var \App\Models\User $user */
        $user = $this->route('user');
        return route('admin.attendance.detail', ['id' => $user->id]) . '?date=' . $date;
    }
}
