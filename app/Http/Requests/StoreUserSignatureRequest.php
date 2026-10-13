<?php

namespace App\Http\Requests;

use App\Services\SignatureStorage;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Data URL PNG tanda tangan yang disimpan ke profil akun.
 *
 * Sesuai dengan payload canvas di applicat ini, bukan upload multipart: tidak
 * ada file sementara di server dan tidak butuh storage:link. Validasi PNG
 * yang sebenarnya tetap ditegakkan ulang di SignatureStorage::decode(), supaya
 * isi file tidak pernah dianggap benar hanya karena lolos regex.
 */
class StoreUserSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signature' => [
                'required',
                'string',
                'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/',
                'max:' . SignatureStorage::MAX_CHARS,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'signature.required' => 'Gambar tanda tangan wajib digambar.',
            'signature.regex' => 'Tanda tangan tidak valid. Silakan gambar ulang.',
            'signature.max' => 'Ukuran gambar tanda tangan terlalu besar.',
        ];
    }
}