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
            $rangeInvalid = false;

            foreach ($brs as $i => $b) {
                $s = $toMin($b['start'] ?? null);
                $e = $toMin($b['end'] ?? null);

                if ($s !== null && $e !== null && $e < $s) $rangeInvalid = true;
                if ($in  !== null && $s !== null && $s < $in) $rangeInvalid = true;
                if ($in  !== null && $e !== null && $e < $in) $rangeInvalid = true;
                if ($out !== null && $s !== null && $s > $out) $rangeInvalid = true;
                if ($out !== null && $e !== null && $e > $out) $rangeInvalid = true;
            }

            // 出勤>退勤の不整合（clock_pair はそのまま）
            if ($in !== null && $out !== null && $in > $out) {
                $v->errors()->add('clock_pair', '出勤時間もしくは退勤時間が不適切な値です');
            }

            if ($rangeInvalid) {
                $errors = $v->errors();

                // ★ 休憩の個別エラー（date_format含む）をすべて除去
                foreach (array_keys($errors->toArray()) as $key) {
                    if (preg_match('/^breaks\.\d+\.(start|end)$/', $key)) {
                        $errors->forget($key);
                    }
                }
                // ★ 共通キーだけ追加
                if (! $errors->has('breaks_range')) {
                    $errors->add('breaks_range', '休憩時間が不適切な値です');
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
