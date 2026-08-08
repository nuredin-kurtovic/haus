<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SupportChatRequest extends FormRequest
{
    /** Javna ruta, bez auth-a. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'messages' => ['required', 'array', 'min:1', 'max:20'],
            'messages.*.role' => ['required', 'string', Rule::in(['user', 'assistant'])],
            'messages.*.content' => ['required', 'string', 'min:1', 'max:2000'],
        ];
    }

    /**
     * Razgovor mora poceti i zavrsiti korisnikovom porukom. Model odgovara
     * na zadnju poruku, pa zadnja mora biti user. Prva mora biti user jer
     * API ne prima razgovor koji pocinje asistentom.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $messages = $this->input('messages');

                if (! is_array($messages) || $messages === []) {
                    return;
                }

                $first = $messages[array_key_first($messages)] ?? null;
                $last = $messages[array_key_last($messages)] ?? null;

                if (! is_array($first) || ($first['role'] ?? null) !== 'user') {
                    $validator->errors()->add('messages', 'Razgovor mora početi vašim pitanjem.');
                }

                if (! is_array($last) || ($last['role'] ?? null) !== 'user') {
                    $validator->errors()->add('messages', 'Zadnja poruka mora biti vaše pitanje.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'messages.required' => 'Poruka je obavezna.',
            'messages.max' => 'Razgovor je predug. Osvježite stranicu i počnite ispočetka.',
            'messages.*.content.max' => 'Pitanje je predugo. Skratite ga na 2000 znakova.',
            'messages.*.role.in' => 'Neispravan oblik razgovora.',
        ];
    }

    /**
     * Cist niz za API: samo role i content, u redoslijedu kako su stigli.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function razgovor(): array
    {
        return array_values(array_map(
            fn (array $message) => [
                'role' => (string) $message['role'],
                'content' => (string) $message['content'],
            ],
            $this->input('messages')
        ));
    }
}
