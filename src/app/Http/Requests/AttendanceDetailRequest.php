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
        $this->merge([
            'date' => $this->route('date') ?? $this->input('date'),
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

            // 休憩の整合性チェック（既存ロジック維持）
            $brs = $this->input('breaks', []);
            foreach ($brs as $i => $b) {
                $s = $toMin($b['start'] ?? null);
                $e = $toMin($b['end'] ?? null);

                if ($s !== null && $e !== null && $e < $s) {
                    $v->errors()->add("breaks.$i.start", '休憩時間が不適切な値です');
                    $v->errors()->add("breaks.$i.end",   '休憩時間が不適切な値です');
                }
                if ($out !== null) {
                    if ($s !== null && $s > $out) $v->errors()->add("breaks.$i.start", '休憩時間が不適切な値です');
                    if ($e !== null && $e > $out) $v->errors()->add("breaks.$i.end",   '休憩時間が不適切な値です');
                }
            }

            // ===== 「出勤/退勤のエラーを1本化」 =====
            $errors = $v->errors();

            $formatInvalid = $errors->has('clock_in') || $errors->has('clock_out');
            $orderInvalid  = ($in !== null && $out !== null && $in > $out);

            if ($orderInvalid) {
                // 両方入力されていて前後関係が不正
                $errors->add('clock_pair', '出勤時間もしくは退勤時間が不適切な値です');
            }

            if ($formatInvalid) {
                // どちらかのフォーマットが不正でも共通キーへ
                $errors->add('clock_pair', '出勤時間もしくは退勤時間が不適切な値です');
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
