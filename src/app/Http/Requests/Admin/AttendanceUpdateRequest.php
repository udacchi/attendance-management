<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class AdminAttendanceUpdateRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'clock_in'      => ['nullable', 'date_format:H:i'],
            'clock_out'     => ['nullable', 'date_format:H:i'],
            'break1_start'  => ['nullable', 'date_format:H:i'],
            'break1_end'    => ['nullable', 'date_format:H:i'],
            'break2_start'  => ['nullable', 'date_format:H:i'],
            'break2_end'    => ['nullable', 'date_format:H:i'],
            'note'          => ['required', 'string', 'max:1000'], // 4) 備考必須
        ];
    }

    public function messages()
    {
        return [
            'note.required' => '備考を記入してください',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            // ここがポイント：date はクエリ (?date=YYYY-MM-DD) から取得
            $date = $this->query('date'); // ex) 2025-10-26
            if (!$date) return; // 念のため

            $toDT = function ($hm) use ($date) {
                return $hm ? Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $hm) : null;
            };

            $in   = $toDT($this->input('clock_in'));
            $out  = $toDT($this->input('clock_out'));
            $b1s  = $toDT($this->input('break1_start'));
            $b1e  = $toDT($this->input('break1_end'));
            $b2s  = $toDT($this->input('break2_start'));
            $b2e  = $toDT($this->input('break2_end'));

            // 1) 出勤/退勤の前後
            if ($in && $out && $in->gt($out)) {
                $v->errors()->add('clock_in',  '出勤時間もしくは退勤時間が不適切な値です');
                $v->errors()->add('clock_out', '出勤時間もしくは退勤時間が不適切な値です');
            }

            // 2) 休憩開始は出勤〜退勤の間
            foreach ([['break1_start', $b1s], ['break2_start', $b2s]] as $pair) {
                [$field, $start] = $pair;
                if ($start && (($in && $start->lt($in)) || ($out && $start->gt($out)))) {
                    $v->errors()->add($field, '休憩時間が不適切な値です');
                }
            }

            // 3) 休憩終了は対応開始より後、かつ退勤を超えない
            foreach ([['break1_end', $b1e, $b1s], ['break2_end', $b2e, $b2s]] as $triple) {
                [$field, $end, $start] = $triple;
                if ($end && (($start && $end->lte($start)) || ($out && $end->gt($out)))) {
                    $v->errors()->add($field, '休憩時間もしくは退勤時間が不適切な値です');
                }
            }
        });
    }
}
