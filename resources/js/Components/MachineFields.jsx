import NeuChip from '@/Components/NeuChip';

/**
 * Satu-satunya sumber markup untuk kolom mesin, dipakai bersama oleh form
 * "Tambah Mesin" dan form "Ubah" supaya keduanya tidak bisa berbeda isian.
 *
 * Komponen ini mengembalikan fragment, bukan pembungkus, sehingga isinya
 * langsung menjadi anak grid milik form pemanggil.
 */
export default function MachineFields({
    data,
    setData,
    clearErrors = () => {},
    errors = {},
    types = [],
    templates = [],
    categories = [],
    subCategories = [],
    onSubCategoryChange,
    idPrefix = 'mesin',
}) {
    const inputProps = (name) => ({
        id: `${idPrefix}-${name}`,
        value: data[name] ?? '',
        onChange: (e) => {
            clearErrors(name);
            setData(name, e.target.value);
        },
        className: `neu-input ${errors[name] ? '!shadow-neu-in !ring-1 !ring-neu-bad/60' : ''}`,
        'aria-invalid': !!errors[name],
        'aria-describedby': errors[name] ? `${idPrefix}-err-${name}` : undefined,
    });

    const Field = ({ name, children }) => (
        <div>
            {children}
            {errors[name] && (
                <p id={`${idPrefix}-err-${name}`} className="mt-1 text-[11px] font-bold text-neu-bad">
                    {errors[name]}
                </p>
            )}
        </div>
    );

    const isActive = !!Number(data.is_active);

    return (
        <>
            <Field name="code">
                <input type="text" placeholder="Kode (mis. MC.7-LCS30-103)" {...inputProps('code')} required />
            </Field>

            <Field name="name">
                <input type="text" placeholder="Nama mesin" {...inputProps('name')} required />
            </Field>

            <Field name="location">
                <input type="text" placeholder="Lokasi (opsional)" {...inputProps('location')} />
            </Field>

            <Field name="category">
                <input
                    type="text"
                    placeholder="Kategori (opsional)"
                    list={`${idPrefix}-category-options`}
                    {...inputProps('category')}
                />
                <datalist id={`${idPrefix}-category-options`}>
                    {categories.map((c) => (
                        <option key={c} value={c} />
                    ))}
                </datalist>
            </Field>

            <Field name="sub_category">
                <input
                    type="text"
                    placeholder="Sub-kategori (opsional, mis. C.7)"
                    list={`${idPrefix}-sub-options`}
                    {...inputProps('sub_category')}
                    onChange={(e) => {
                        clearErrors('sub_category');
                        // Angka pada sub-kategori sudah memuat nomor minggu,
                        // jadi ketikan di sini langsung mengisi pilihan minggu.
                        if (onSubCategoryChange) {
                            onSubCategoryChange(e.target.value);
                        } else {
                            setData('sub_category', e.target.value);
                        }
                    }}
                />
                <datalist id={`${idPrefix}-sub-options`}>
                    {subCategories.map((s) => (
                        <option key={s} value={s} />
                    ))}
                </datalist>
            </Field>

            <Field name="type">
                <select {...inputProps('type')}>
                    {types.map((t) => (
                        <option key={t.value} value={t.value}>{t.label}</option>
                    ))}
                </select>
            </Field>

            <Field name="week_group">
                <select {...inputProps('week_group')} onChange={(e) => {
                    clearErrors('week_group');
                    setData('week_group', parseInt(e.target.value, 10));
                }}>
                    {[1, 2, 3, 4, 5, 6, 7, 8, 9].map((w) => (
                        <option key={w} value={w}>Minggu {w}</option>
                    ))}
                </select>
            </Field>

            <div className="sm:col-span-2">
                <Field name="template_id">
                    <select {...inputProps('template_id')}>
                        <option value="">Tanpa template (pakai checklist bawaan)</option>
                        {templates.map((t) => (
                            <option key={t.id} value={t.id}>{t.name}</option>
                        ))}
                    </select>
                </Field>
            </div>

            {/* Status memakai chip, bukan checkbox, supaya tetap konsisten dengan
                filter area di halaman ini. Nilainya dikirim sebagai 1/0 karena
                aturan validasi `boolean` menolak string "false". */}
            <div className="sm:col-span-2">
                <NeuChip
                    active={isActive}
                    onClick={() => setData('is_active', isActive ? 0 : 1)}
                    aria-label="Status mesin"
                >
                    {isActive ? 'Aktif' : 'Nonaktif'}
                </NeuChip>
                <p className="mt-1.5 text-[11px] text-neu-sub">
                    Mesin nonaktif tetap tersimpan, tetapi tidak dihitung di dashboard dan laporan.
                </p>
            </div>
        </>
    );
}