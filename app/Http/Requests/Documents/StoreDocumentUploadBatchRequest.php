<?php

namespace App\Http\Requests\Documents;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentUploadBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        /*
         * Проверка дела и его филиальных прав выполняется
         * в контроллере и повторяется внутри транзакции.
         */
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            /*
             * unassigned — верхняя общая кнопка;
             * typed — кнопка внутри категории.
             */
            'placement' => [
                'required',
                'in:unassigned,typed',
            ],

            /*
             * UUID генерируется браузером один раз.
             * При повторной попытке используется тот же UUID.
             */
            'client_batch_key' => [
                'required',
                'uuid',
            ],

            'document_type_id' => [
                'required_if:placement,typed',
                'prohibited_if:placement,unassigned',
                'nullable',
                'integer',
                'min:1',
            ],

            /*
             * Указывается только при пополнении
             * существующего комплекта фотографий.
             */
            'document_id' => [
                'prohibited_if:placement,unassigned',
                'nullable',
                'integer',
                'min:1',
            ],

            'title' => [
                'prohibited_if:placement,unassigned',
                'nullable',
                'string',
                'max:255',
            ],

            'document_date' => [
                'prohibited_if:placement,unassigned',
                'nullable',
                'date_format:Y-m-d',
            ],

            'files' => [
                'required',
                'array',
                'min:1',
                'max:' . (int) config(
                    'documents.uploads.max_files_per_batch',
                    500
                ),
            ],

            'files.*.client_file_key' => [
                'required',
                'uuid',
                'distinct',
            ],

            'files.*.name' => [
                'required',
                'string',
                'max:255',
            ],

            'files.*.size' => [
                'required',
                'integer',
                'min:1',
                'max:' . (int) config(
                    'documents.uploads.max_file_bytes'
                ),
            ],

            /*
             * MIME от браузера — только подсказка.
             * Для доверенной проверки использовать его нельзя.
             */
            'files.*.mime_type' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'document_type_id.required_if' =>
                'Выберите тип документа.',

            'files.required' =>
                'Выберите файлы для загрузки.',

            'files.max' =>
                'Превышено количество файлов в одном пакете.',

            'files.*.size.max' =>
                'Один из файлов превышает допустимый размер.',

            'files.*.size.min' =>
                'Пустые файлы не принимаются.',

            'files.*.client_file_key.distinct' =>
                'В пакете повторяется идентификатор файла.',
        ];
    }
}