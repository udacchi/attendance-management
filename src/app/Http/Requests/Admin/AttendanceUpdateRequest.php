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
            'date'               => ['required', 'date'],
            'clock_in'           => ['nullable', 'date_format:H:i'],
            'clock_out'          => ['nullable', 'date_format:H:i'],

            // 休憩は配列 + H:i（個別エラーは立つが Blade では表示しない）
            'breaks'             => ['sometimes', 'array'],
            'breaks.*.start'     => ['nullable', 'date_format:H:i'],
            'breaks.*.end'       => ['nullable', 'date_format:H:i'],

            'note'               => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required'              => '日付が取得できませんでした',
            'date.date'                  => '日付の形式が不正です',

            // （個別は立っても Blade で出さない）
            'clock_in.date_format'       => '出勤時間が不適切な値です',
            'clock_out.date_format'      => '退勤時間が不適切な値です',
            'breaks.*.start.date_format' => '休憩時間が不適切な値です',
            'breaks.*.end.date_format'   => '休憩時間が不適切な値です',

            'note.required'              => '備考を記入してください',
        ];
    }

    protected function prepareForValidation(): void
    {
        // 旧UI（break1_*, break2_*）を breaks[] に取り込み
        $breaks = (array) $this->input('breaks', []);

        $add = function ($s, $e) use (&$breaks) {
            $s = $s !== null ? trim((string)$s) : null;
            $e = $e !== null ? trim((string)$e) : null;
            if (($s ?? '') === '' && ($e ?? '') === '') return;
            $breaks[] = ['start' => $s, 'end' => $e];
        };

        $add($this->input('break1_start'), $this->input('break1_end'));
        $add($this->input('break2_start'), $this->input('break2_end'));

        $this->merge(['breaks' => $breaks]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $date = $this->query('date') ?: $this->input('date');
            if (!$date) return;

            $tz = config('app.timezone', 'Asia/Tokyo');
            $toDT = function (?string $hm) use ($date, $tz) {
                if (!$hm || strpos($hm, ':') === false) return null;
                try {
                    return Carbon::createFromFormat('Y-m-d H:i', "$date $hm", $tz)->second(0);
                } catch (\Throwable $e) {
                    return null;
                }
            };

            $in  = $toDT($this->input('clock_in'));
            $out = $toDT($this->input('clock_out'));
            $errors = $v->errors();

            // ===== 出勤・退勤：集約キー clock_pair だけ追加 =====
            $pairInvalid = ($in && $out && $in->gt($out));
            $formatInvalid = $errors->has('clock_in') || $errors->has('clock_out');

            if ($pairInvalid || $formatInvalid) {
                if (!$errors->has('clock_pair')) {
                    $errors->add('clock_pair', '出勤時間もしくは退勤時間が不適切な値です');
                }
            }

            // ===== 休憩：集約キー breaks_range だけ追加 =====
            $breaks = (array) $this->input('breaks', []);
            $rangeInvalid = false;
            $overOut      = false;

            foreach ($breaks as $row) {
                $s = $toDT($row['start'] ?? null);
                $e = $toDT($row['end']   ?? null);
                if (!$s && !$e) continue; // 空行

                // 片方欠落 or 並び不正
                if (!$s || !$e || $e->lte($s)) {
                    $rangeInvalid = true;
                    continue;
                }
                if ($in  && $s->lt($in))  $rangeInvalid = true;
                if ($out && $s->gt($out)) $rangeInvalid = true;
                if ($out && $e->gt($out)) {
                    $rangeInvalid = true;
                    $overOut = true;
                }
            }

            if ($rangeInvalid) {
                $msg = $overOut
                    ? '休憩時間もしくは退勤時間が不適切な値です'
                    : '休憩時間が不適切な値です';
                if (!$errors->has('breaks_range')) {
                    $errors->add('breaks_range', $msg);
                }
            }
        });
    }
}
