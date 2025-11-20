<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class AttendanceDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // auth ミドルウェアで守られている前提
    }

    public function rules(): array
    {
        return [
            // 出勤・退勤
            'clock_in'  => ['nullable', 'date_format:H:i'],
            'clock_out' => ['nullable', 'date_format:H:i'],

            // 休憩（配列）
            'breaks'               => ['array'],
            'breaks.*.start'       => ['nullable', 'date_format:H:i'],
            'breaks.*.end'         => ['nullable', 'date_format:H:i'],

            // 備考
            'note' => ['required', 'string', 'max:255'],  // FN029-4
        ];
    }

    public function messages(): array
    {
        return [
            'clock_in.date_format'  => '出勤時間は HH:MM 形式で入力してください。',
            'clock_out.date_format' => '退勤時間は HH:MM 形式で入力してください。',
            'breaks.*.start.date_format' => '休憩時間は HH:MM 形式で入力してください。',
            'breaks.*.end.date_format'   => '休憩時間は HH:MM 形式で入力してください。',
            'note.required' => '備考を記入してください。', // FN029-4
        ];
    }

    /**
     * FN029 の論理チェックをここで行う
     */
    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $clockIn  = $this->input('clock_in');
            $clockOut = $this->input('clock_out');
            $breaks   = $this->input('breaks', []);

            // HH:MM → Carbon 変換の小ヘルパ
            $toTime = function (?string $hhmm) {
                if (!$hhmm) return null;
                return Carbon::createFromFormat('H:i', $hhmm);
            };

            $in  = $toTime($clockIn);
            $out = $toTime($clockOut);

            // --- 1. 出勤 > 退勤 / 退勤 < 出勤 （FN029-1） ---
            if ($in && $out && $in->gte($out)) {
                $v->errors()->add(
                    'clock_in',
                    '出勤時間もしくは退勤時間が不適切な値です'
                );
            }

            // --- 休憩時間のチェック（FN029-2,3） ---
            foreach ($breaks as $idx => $b) {
                $bs = $toTime($b['start'] ?? null);
                $be = $toTime($b['end'] ?? null);

                if (!$bs && !$be) {
                    continue; // 両方空ならスキップ
                }

                // 2. 休憩開始が出勤前 or 退勤後 → メッセージ2
                if ($bs && $in && $bs->lt($in)) {
                    $v->errors()->add(
                        "breaks.$idx.start",
                        '休憩時間が不適切な値です'
                    );
                }
                if ($bs && $out && $bs->gt($out)) {
                    $v->errors()->add(
                        "breaks.$idx.start",
                        '休憩時間が不適切な値です'
                    );
                }

                // 3. 休憩終了が退勤後 → メッセージ3
                if ($be && $out && $be->gt($out)) {
                    $v->errors()->add(
                        "breaks.$idx.end",
                        '休憩時間もしくは退勤時間が不適切な値です'
                    );
                }
            }
        });
    }
}