<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class AttendanceDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    // /attendance/{date}/request の {date} を input に取り込む
    protected function prepareForValidation(): void
    {
        $breaks = $this->input('breaks', []);
        foreach ($breaks as $i => $b) {
            foreach (['start', 'end'] as $k) {
                if (($b[$k] ?? null) === '--:--') {
                    $breaks[$i][$k] = null;
                }
            }
        }
        $this->merge([
            'date'   => $this->route('date') ?? $this->input('date'),
            'breaks' => $breaks,
        ]);
    }


    public function rules(): array
    {
        return [
            'date'               => ['required', 'date'],
            'clock_in'           => ['nullable', 'date_format:H:i'],
            'clock_out'          => ['nullable', 'date_format:H:i'],
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
            // 個別キーの文言は残してOK（最終的に clock_pair にまとめて表示）
            'clock_in.date_format'       => '出勤時間が不適切な値です',
            'clock_out.date_format'      => '退勤時間が不適切な値です',
            'breaks.*.start.date_format' => '休憩時間が不適切な値です',
            'breaks.*.end.date_format'   => '休憩時間が不適切な値です',
            'note.required'              => '備考を記入してください',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $toMin = function ($t) {
                if ($t === null || $t === '') return null;
                if (!str_contains($t, ':')) return null;
                [$h, $m] = explode(':', $t);
                return (int)$h * 60 + (int)$m;
            };

            $in  = $toMin($this->input('clock_in'));
            $out = $toMin($this->input('clock_out'));

            $brs = $this->input('breaks', []);

            $rangeInvalid = false;      // 何らかの範囲外
            $overOut      = false;      // ★ 休憩終了が退勤より後
            foreach ($brs as $b) {
                $s = $toMin($b['start'] ?? null);
                $e = $toMin($b['end'] ?? null);

                // 並び不正（開始>終了）
                if ($s !== null && $e !== null && $e < $s) $rangeInvalid = true;

                // 出勤より前
                if ($in !== null) {
                    if ($s !== null && $s < $in) $rangeInvalid = true;
                    if ($e !== null && $e < $in) $rangeInvalid = true;
                }

                // 退勤より後
                if ($out !== null) {
                    if ($s !== null && $s > $out) $rangeInvalid = true;
                    if ($e !== null && $e > $out) {
                        $rangeInvalid = true;
                        $overOut = true;
                    } // ★
                }
            }

            // 出勤>退勤（別キーで集約）
            if ($in !== null && $out !== null && $in > $out) {
                $v->errors()->add('clock_pair', '出勤時間もしくは退勤時間が不適切な値です');
            }

            if ($rangeInvalid) {
                $errors = $v->errors();

                // 個別（breaks.*.start/end）の既存エラーを抑制
                foreach (array_keys($errors->toArray()) as $key) {
                    if (preg_match('/^breaks\.\d+\.(start|end)$/', $key)) {
                        $errors->forget($key);
                    }
                }

                // ★ メッセージを条件で出し分け（表示は breaks_range 1行だけ）
                $msg = $overOut
                    ? '休憩時間もしくは退勤時間が不適切な値です'   // 休憩終了 > 退勤
                    : '休憩時間が不適切な値です';                 // それ以外の範囲外

                if (!$errors->has('breaks_range')) {
                    $errors->add('breaks_range', $msg);
                } else {
                    // 既に立っている場合も、より強い方（overOut）を優先
                    if ($overOut) {
                        $errors->forget('breaks_range');
                        $errors->add('breaks_range', $msg);
                    }
                }
            }
        });
    }


    // 失敗時は同じ日の詳細へ戻す（値保持）
    protected function getRedirectUrl(): string
    {
        $date = (string)($this->input('date') ?? $this->route('date'));
        return route('attendance.detail', ['date' => $date]);
    }
}
