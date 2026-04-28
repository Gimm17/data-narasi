<?php

namespace App\Http\Requests;

use App\Enums\AnalysisType;
use App\Enums\NarrativeTone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Form Request untuk validasi upload file
 */
class UploadFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Auth check dilakukan di route middleware
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx,xls',
                'extensions:csv,xlsx,xls',
                'max:10240', // 10MB in kilobytes
            ],
            'analysis_type' => [
                'required',
                'string',
                'in:' . implode(',', array_column(AnalysisType::cases(), 'value')),
            ],
            'tone' => [
                'required',
                'string',
                'in:' . implode(',', array_column(NarrativeTone::cases(), 'value')),
            ],
            'title' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors (Bahasa Indonesia)
     */
    public function messages(): array
    {
        return [
            'file.required' => 'File wajib diupload.',
            'file.file' => 'File harus berupa file yang valid.',
            'file.mimes' => 'Format file harus CSV atau Excel (xlsx, xls).',
            'file.extensions' => 'Ekstensi file harus .csv, .xlsx, atau .xls.',
            'file.max' => 'Ukuran file maksimal 10MB.',

            'analysis_type.required' => 'Tipe analisis wajib dipilih.',
            'analysis_type.in' => 'Tipe analisis tidak valid.',

            'tone.required' => 'Tone narasi wajib dipilih.',
            'tone.in' => 'Tone narasi tidak valid.',

            'title.string' => 'Judul harus berupa teks.',
            'title.max' => 'Judul maksimal 255 karakter.',
        ];
    }

    /**
     * Get custom attributes for validator errors
     */
    public function attributes(): array
    {
        return [
            'file' => 'File',
            'analysis_type' => 'Tipe Analisis',
            'tone' => 'Tone Narasi',
            'title' => 'Judul',
        ];
    }

    /**
     * Ambil enum AnalysisType dari input
     */
    public function getAnalysisType(): AnalysisType
    {
        return AnalysisType::from($this->input('analysis_type'));
    }

    /**
     * Ambil enum NarrativeTone dari input
     */
    public function getTone(): NarrativeTone
    {
        return NarrativeTone::from($this->input('tone'));
    }

    /**
     * Ambil title atau generate dari filename
     */
    public function getTitle(): string
    {
        $customTitle = $this->input('title');

        if (!empty($customTitle)) {
            return $customTitle;
        }

        // Generate title dari filename tanpa ekstensi
        $filename = $this->file('file')->getClientOriginalName();
        return pathinfo($filename, PATHINFO_FILENAME);
    }
}
