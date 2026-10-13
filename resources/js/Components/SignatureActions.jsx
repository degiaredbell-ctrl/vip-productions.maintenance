import NeuButton from '@/Components/NeuButton';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuSignaturePad from '@/Components/NeuSignaturePad';
import useSignatureActions from '@/Components/useSignatureActions';

/**
 * Panel aksi tanda tangan sebagai satu kartu utuh, untuk tahap User PIC dan
 * Atasan di halaman form PM.
 *
 * Tahap 1 (teknisi) tidak memakai komponen ini: penandatanganannya ada di akhir
 * form checklist supaya teknisi cukup satu kali submit. Backend tetap memvalidasi
 * tahap mana yang boleh dikerjakan.
 *
 * Antrean Persetujuan tidak memakai komponen ini. Daftar itu hanya antrean:
 * Persetujuan dilakukan di halaman ini, tempat checklist yang disetujui
 * dibaca bersamaan dengan tanda tangannya.
 *
 * Tanda tangan punya dua sumber (opsional, dipilih user):
 *
 * 1. "Tanda tangan tersimpan" — PNG yang disimpan user di halaman Profile.
 *    Approve langsung dengan satu klik, tanpa menggambar ulang.
 * 2. "Gambar baru" — canvas seperti semula; user bisa sekaligus menyimpan
 *    hasilnya ke profil agar dipakai lagi di persetujuan berikutnya.
 *
 * Tidak ada field nama. Approver sudah login atas namanya sendiri, jadi
 * `signerName` datang dari akun dan backend memakainya apa adanya — nama yang
 * dikirim client untuk tahap persetujuan diabaikan supaya tidak bisa dipalsukan.
 */
export default function SignatureActions({ recordId, stage, canSign, canReject, signerName = '', savedSignature = null }) {
    const {
        padRef,
        note,
        signatureReady,
        busy,
        processing,
        message,
        signerNameFilled,
        mode,
        saveToProfile,
        onNoteChange,
        onSignatureChange,
        onModeChange,
        onSaveToProfileChange,
        handleSign,
        handleReject,
    } = useSignatureActions({ recordId, canSign, canReject, signerName, savedSignature });

    if (!canSign && !canReject) return null;

    const previewUrl = savedSignature ? `${savedSignature.url}?${savedSignature.version}` : null;

    return (
        <NeuCard className="mb-4">
            <b className="text-sm block mb-0.5">{stage.label}</b>
            <p className="text-xs text-neu-sub mb-4">
                {canSign
                    ? 'Periksa checklist di atas, lalu beri tanda tangan untuk melanjutkan ke tahap berikutnya.'
                    : 'Anda dapat menolak PM ini dengan menyertakan alasan.'}
            </p>

            {canSign && (
                <div className="mb-4">
                    {/*
                        Nama diambil dari akun login, jadi ditampilkan sebagai
                        teks, bukan input: tidak ada yang perlu diketik dan tidak
                        ada yang bisa diisi dengan nama orang lain.
                    */}
                    <p className="text-xs text-neu-sub mb-3">
                        Nama Penanda Tangan{' '}
                        <span className="text-neu-bad">*</span>
                        <b className="block text-sm text-neu-text mt-0.5">{signerName}</b>
                    </p>

                    {savedSignature && (
                        <>
                            <div className="flex flex-wrap gap-2 mb-3">
                                <NeuChip
                                    size="sm"
                                    active={mode === 'saved'}
                                    onClick={() => onModeChange('saved')}
                                >
                                    Tanda tangan tersimpan
                                </NeuChip>
                                <NeuChip
                                    size="sm"
                                    active={mode === 'draw'}
                                    onClick={() => onModeChange('draw')}
                                >
                                    Gambar baru
                                </NeuChip>
                            </div>

                            {mode === 'saved' ? (
                                <>
                                    <p className="text-xs text-neu-sub mb-1.5">
                                        Tanda tangan dari halaman Profile akan dipakai.
                                    </p>
                                    <div className="neu-inset rounded-neu-sm p-3 flex items-center justify-center">
                                        <img
                                            src={previewUrl}
                                            alt="Tanda tangan tersimpan"
                                            className="max-h-28"
                                        />
                                    </div>
                                </>
                            ) : (
                                <>
                                    <NeuSignaturePad
                                        ref={padRef}
                                        disabled={busy}
                                        error={message?.field === 'signature'}
                                        onChange={onSignatureChange}
                                    />

                                    <label className="flex items-start gap-2 mt-3 text-xs text-neu-sub cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={saveToProfile}
                                            onChange={(e) => onSaveToProfileChange(e.target.checked)}
                                            disabled={busy}
                                            className="mt-1 accent-neu-accent"
                                        />
                                        <span>
                                            Simpan tanda tangan ini di profil saya agar bisa
                                            dipakai lagi tanpa menggambar ulang.
                                        </span>
                                    </label>
                                </>
                            )}
                        </>
                    )}

                    {!savedSignature && (
                        <>
                            <NeuSignaturePad
                                ref={padRef}
                                disabled={busy}
                                error={message?.field === 'signature'}
                                onChange={onSignatureChange}
                            />

                            <label className="flex items-start gap-2 mt-3 text-xs text-neu-sub cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={saveToProfile}
                                    onChange={(e) => onSaveToProfileChange(e.target.checked)}
                                    disabled={busy}
                                    className="mt-1 accent-neu-accent"
                                />
                                <span>
                                    Simpan tanda tangan ini di profil saya agar bisa
                                    dipakai lagi tanpa menggambar ulang.
                                </span>
                            </label>
                        </>
                    )}
                </div>
            )}

            <label htmlFor="sign-note" className="block text-xs text-neu-sub mb-1.5">
                {canReject ? 'Catatan' : 'Catatan (opsional)'}
            </label>
            <textarea
                id="sign-note"
                value={note}
                onChange={(e) => onNoteChange(e.target.value)}
                rows={2}
                maxLength={500}
                disabled={busy}
                placeholder={canReject ? 'Tuliskan alasan penolakan' : 'Catatan tambahan (opsional)'}
                className="neu-input min-h-[64px] resize-y"
            />

            {message && (
                <p id="signature-error" role="alert" className="text-sm text-neu-bad font-semibold mt-3">
                    {message.text}
                </p>
            )}

            <div className="flex flex-col gap-2.5 mt-4">
                {canSign && (
                    <>
                        <NeuButton
                            variant="primary"
                            className="w-full !py-3.5"
                            disabled={busy || !signerNameFilled || !signatureReady}
                            onClick={handleSign}
                        >
                            {processing === 'sign'
                                ? 'Menyimpan tanda tangan…'
                                : stage.value === 'pic'
                                    ? 'Setujui & Tanda tangan'
                                    : 'Tanda tangan & Setujui'}
                        </NeuButton>

                        {/* Sama seperti tahap teknisi: tombol mati sampai tanda
                            tangan ada, jadi tidak ada approval tanpa bukti. */}
                        {!busy && !signatureReady && signerNameFilled && (
                            <p className="text-xs text-neu-sub text-center">
                                {mode === 'saved'
                                    ? 'Pilih tanda tangan dari profil atau gambar baru.'
                                    : 'Tanda tangan wajib digambar.'}
                            </p>
                        )}
                    </>
                )}

                {canReject && (
                    <NeuButton
                        className="w-full !py-3.5 text-neu-bad"
                        disabled={busy}
                        onClick={handleReject}
                    >
                        {processing === 'reject' ? 'Mengirim…' : 'Tolak & Kembalikan ke Teknisi'}
                    </NeuButton>
                )}
            </div>
        </NeuCard>
    );
}