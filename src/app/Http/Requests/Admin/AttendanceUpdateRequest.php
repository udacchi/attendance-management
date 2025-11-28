<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Carbon\Carbon;

class AttendanceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // 管理ガードでログインしていればOK（運用に合わせて調整可）
        return auth('admin')->check() || auth()->check();
    }

    public function rules(): array
    {
        return [
            'clock_in'      => ['nullable', 'date_format:H:i'],
            'clock_out'     => ['nullable', 'date_format:H:i'],
            'break1_start'  => ['nullable', 'date_format:H:i'],
            'break1_end'    => ['nullable', 'date_format:H:i'],
            'break2_start'  => ['nullable', 'date_format:H:i'],
            'break2_end'    => ['nullable', 'date_format:H:i'],
            // ★ テストの想定に合わせて「未入力でも通す」
            'note'          => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'clock_in.date_format'   => '時刻はHH:MM形式で入力してください',
            'clock_out.date_format'  => '時刻はHH:MM形式で入力してください',
            'break1_start.date_format' => '時刻はHH:MM形式で入力してください',
            'break1_end.date_format'   => '時刻はHH:MM形式で入力してください',
            'break2_start.date_format' => '時刻はHH:MM形式で入力してください',
            'break2_end.date_format'   => '時刻はHH:MM形式で入力してください',
            'note.max'               => '備考は255文字以内で入力してください',
        ];
    }

    protected function prepareForValidation(): void
    {
        $breaks = $this->input('breaks', []);

        $addPair = function ($s, $e) use (&$breaks) {
            $s = $s !== null ? trim((string)$s) : null;
            $e = $e !== null ? trim((string)$e) : null;
            if ($s === null && $e === null) return;
            if ($s === ''   && $e === '')   return;
            $breaks[] = ['start' => $s, 'end' => $e];
        };

        $addPair($this->input('break1_start'), $this->input('break1_end'));
        $addPair($this->input('break2_start'), $this->input('break2_end'));

        $this->merge(['breaks' => $breaks]);
    }

    /**
     * 相関チェック（退勤より後の休憩開始/終了など）
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $date = $this->query('date') ?: $this->input('date');
            if (!$date) return;

            $tz = config('app.timezone', 'Asia/Tokyo');
            $toDT = function (?string $hm) use ($date, $tz) {
                if (!$hm) return null;
                try {
                    return \Carbon\Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $hm, $tz)->second(0);
                } catch (\Throwable $e) {
                    return null;
                }
            };

            $in  = $toDT($this->input('clock_in'));
            $out = $toDT($this->input('clock_out'));

            // ① 出勤 > 退勤 はNG（両方に同じメッセージ）
            if ($in && $out && $in->gt($out)) {
                $msg = '出勤時間もしくは退勤時間が不適切な値です';
                $v->errors()->add('clock_in',  $msg);
                $v->errors()->add('clock_out', $msg);
            }

            // prepareForValidation 済みの breaks[] を使う
            $breaks = (array)$this->input('breaks', []);
            foreach ($breaks as $idx => $row) {
                $startRaw = isset($row['start']) ? trim((string)$row['start']) : '';
                $endRaw   = isset($row['end'])   ? trim((string)$row['end'])   : '';

                // 完全空行はスキップ
                if ($startRaw === '' && $endRaw === '') continue;

                $s = $toDT($row['start'] ?? null);
                $e = $toDT($row['end']   ?? null);

                // ここからエラーフラグを積み上げて最後にキーへ付与
                $errStart = false;
                $errEnd   = false;
                $msgStart = '休憩時間が不適切な値です';
                $msgEnd   = '休憩時間もしくは退勤時間が不適切な値です';

                // 型不正/片方欠落
                if (!$s || !$e) {
                    $errStart = true;
                    $errEnd   = true;
                    $msgStart = '休憩時間が不適切な値です';
                    $msgEnd   = '休憩時間が不適切な値です';
                } else {
                    // ② 終了は開始より後
                    if ($e->lte($s)) {
                        $errEnd = true;
                    }
                    // ③ 開始は出勤〜退勤の範囲内
                    if ($in && $s->lt($in)) {
                        $errStart = true;
                    }
                    if ($out && $s->gt($out)) {
                        $errStart = true;
                    }
                    // ④ 終了は退勤を超えない
                    if ($out && $e->gt($out)) {
                        $errEnd = true;
                    }
                }

                // 標準（配列）キーに付与
                if ($errStart) $v->errors()->add("breaks.$idx.start", $msgStart);
                if ($errEnd)   $v->errors()->add("breaks.$idx.end",   $msgEnd);

                // ★ テスト互換用ミラー：最初の休憩行 idx=0 は break1_* にも同じエラーを付ける
                if ($idx === 0) {
                    if ($errStart) $v->errors()->add('break1_start', $msgStart);
                    if ($errEnd)   $v->errors()->add('break1_end',   $msgEnd);
                }
            }
        });
    }
}
