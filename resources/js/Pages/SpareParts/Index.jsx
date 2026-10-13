import { useMemo, useRef, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuChip from '@/Components/NeuChip';
import NeuPill from '@/Components/NeuPill';
import Modal from '@/Components/Modal';

const EMPTY = {
    code: '', name: '', category: '', brand: '', type: '', description: '',
    unit: '', qty: 0, stock: 0, stock_start: 0, min_stock: 0,
    supplier: '', needed_for: '', note: '', location_code: '', location: '',
};

const FIELD_CLASS = 'neu-input !min-h-[40px] !py-2 text-sm';

function Field({ label, children, error, className = '' }) {
    return (
        <label className={`block ${className}`}>
            <span className="block text-[11px] font-bold uppercase tracking-wide text-neu-sub mb-1">{label}</span>
            {children}
            {error && <span className="mt-1 block text-[11px] font-bold text-neu-bad">{error}</span>}
        </label>
    );
}

export default function SparePartsIndex({ auth, parts = [], categories = [], locations = [], stats = {}, can = {} }) {
    const [q, setQ] = useState('');
    const [category, setCategory] = useState('all');
    const [location, setLocation] = useState('all');
    const [status, setStatus] = useState('all');

    const [formOpen, setFormOpen] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [confirmingId, setConfirmingId] = useState(null);

    const [movePart, setMovePart] = useState(null);
    const [historyPart, setHistoryPart] = useState(null);
    const [historyRows, setHistoryRows] = useState([]);
    const [historyLoading, setHistoryLoading] = useState(false);

    const [importError, setImportError] = useState('');
    const fileRef = useRef(null);

    const form = useForm({ ...EMPTY });
    const moveForm = useForm({ type: 'in', qty: 1, moved_at: '', note: '' });

    const filtered = useMemo(() => {
        const needle = q.trim().toLowerCase();
        return parts.filter((p) => {
            if (category !== 'all' && p.category !== category) return false;
            if (location !== 'all' && p.location !== location) return false;
            if (status === 'low' && !p.low_stock) return false;
            if (status === 'out' && p.stock > 0) return false;
            if (!needle) return true;
            return [p.code, p.name, p.category, p.brand, p.type, p.supplier, p.location]
                .filter(Boolean)
                .some((v) => String(v).toLowerCase().includes(needle));
        });
    }, [parts, q, category, location, status]);

    const openCreate = () => {
        setEditingId(null);
        form.clearErrors();
        form.setData({ ...EMPTY });
        setFormOpen(true);
    };

    const openEdit = (part) => {
        setEditingId(part.id);
        form.clearErrors();
        form.setData({
            code: part.code ?? '',
            name: part.name ?? '',
            category: part.category ?? '',
            brand: part.brand ?? '',
            type: part.type ?? '',
            description: part.description ?? '',
            unit: part.unit ?? '',
            qty: part.qty ?? 0,
            stock: part.stock ?? 0,
            stock_start: part.stock_start ?? 0,
            min_stock: part.min_stock ?? 0,
            supplier: part.supplier ?? '',
            needed_for: part.needed_for ?? '',
            note: part.note ?? '',
            location_code: part.location_code ?? '',
            location: part.location ?? '',
        });
        setFormOpen(true);
    };

    const submitForm = (e) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => setFormOpen(false),
        };
        if (editingId) {
            form.put(route('spareparts.update', editingId), options);
        } else {
            form.post(route('spareparts.store'), options);
        }
    };

    const confirmDelete = (id) => {
        router.delete(route('spareparts.destroy', id), {
            preserveScroll: true,
            onFinish: () => setConfirmingId(null),
        });
    };

    const openMove = (part, type) => {
        setMovePart(part);
        moveForm.clearErrors();
        moveForm.setData({ type, qty: 1, moved_at: '', note: '' });
    };

    const submitMove = (e) => {
        e.preventDefault();
        moveForm.post(route('spareparts.movements.store', movePart.id), {
            preserveScroll: true,
            onSuccess: () => { setMovePart(null); moveForm.reset(); },
        });
    };

    const openHistory = (part) => {
        setHistoryPart(part);
        setHistoryRows([]);
        setHistoryLoading(true);
        window.axios.get(route('spareparts.movements', part.id))
            .then((res) => setHistoryRows(res.data.movements ?? []))
            .finally(() => setHistoryLoading(false));
    };

    const onFile = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setImportError('');
        const data = new FormData();
        data.append('file', file);
        router.post(route('spareparts.import'), data, {
            preserveScroll: true,
            forceFormData: true,
            onError: (errs) => setImportError(errs.file ?? 'Impor gagal. Periksa format CSV.'),
            onFinish: () => { if (fileRef.current) fileRef.current.value = ''; },
        });
    };

    const statusChips = [
        { key: 'all', label: `Semua (${stats.total ?? 0})` },
        { key: 'low', label: `Stok menipis (${stats.low ?? 0})` },
        { key: 'out', label: `Habis (${stats.out ?? 0})` },
    ];

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="page-container">
                <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-neu-sub text-sm">PT Verra Inter Pangan</p>
                        <h1 className="text-xl sm:text-2xl font-bold">Suku Cadang</h1>
                        <p className="text-sm text-neu-sub">
                            {stats.total ?? 0} suku cadang terdaftar
                            {stats.low ? ` · ${stats.low} stok menipis` : ''}
                        </p>
                    </div>
                    {can.manage && (
                        <div className="flex flex-wrap gap-2">
                            <input
                                ref={fileRef}
                                type="file"
                                accept=".csv,text/csv"
                                className="hidden"
                                onChange={onFile}
                            />
                            <NeuButton type="button" variant="ghost" onClick={() => fileRef.current?.click()}>
                                Unggah CSV
                            </NeuButton>
                            <NeuButton type="button" variant="primary" onClick={openCreate}>
                                Tambah Suku Cadang
                            </NeuButton>
                        </div>
                    )}
                </div>

                {importError && (
                    <p className="mb-4 text-sm font-semibold text-neu-bad">{importError}</p>
                )}

                <div className="grid grid-cols-1 md:grid-cols-[1fr_auto_auto] gap-2 mb-3">
                    <input
                        type="search"
                        placeholder="Cari kode, nama, merek, type, atau supplier…"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        className="neu-input"
                        aria-label="Cari suku cadang"
                    />
                    <select value={category} onChange={(e) => setCategory(e.target.value)} className="neu-input" aria-label="Filter kategori">
                        <option value="all">Semua Kategori</option>
                        {categories.map((c) => <option key={c} value={c}>{c}</option>)}
                    </select>
                    <select value={location} onChange={(e) => setLocation(e.target.value)} className="neu-input" aria-label="Filter lokasi">
                        <option value="all">Semua Lokasi</option>
                        {locations.map((l) => <option key={l} value={l}>{l}</option>)}
                    </select>
                </div>

                <div className="flex flex-wrap gap-2 mb-4">
                    {statusChips.map((chip) => (
                        <NeuChip key={chip.key} active={status === chip.key} onClick={() => setStatus(chip.key)}>
                            {chip.label}
                        </NeuChip>
                    ))}
                </div>

                {filtered.length === 0 ? (
                    <NeuCard>
                        <p className="text-sm text-neu-sub text-center py-4">
                            {parts.length === 0
                                ? 'Belum ada suku cadang. Impor CSV atau tambahkan manual.'
                                : 'Tidak ada suku cadang yang cocok dengan filter ini.'}
                        </p>
                    </NeuCard>
                ) : (
                    <div className="neu-table-wrap">
                        <table className="neu-table">
                            <caption className="sr-only">Daftar suku cadang beserta stok terkini.</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Kode</th>
                                    <th scope="col">Suku Cadang</th>
                                    <th scope="col">Kategori</th>
                                    <th scope="col">Stok</th>
                                    <th scope="col">Lokasi</th>
                                    <th scope="col">Supplier</th>
                                    <th scope="col">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                {filtered.map((part) => (
                                    <tr key={part.id}>
                                        <td data-label="Kode" className="neu-cell-lead">
                                            <b className="text-sm">{part.code}</b>
                                        </td>
                                        <td data-label="Suku Cadang">
                                            <b className="block text-sm break-words">{part.name}</b>
                                            <span className="block text-xs text-neu-sub break-words">
                                                {[part.brand, part.type].filter(Boolean).join(' · ') || '—'}
                                            </span>
                                        </td>
                                        <td data-label="Kategori">
                                            <span className="text-xs">{part.category ?? '—'}</span>
                                        </td>
                                        <td data-label="Stok">
                                            <div className="flex items-center gap-2">
                                                <b className="text-sm">{part.stock}</b>
                                                <span className="text-xs text-neu-sub">{part.unit ?? ''}</span>
                                                {part.stock <= 0 ? (
                                                    <NeuPill variant="issue">Habis</NeuPill>
                                                ) : part.low_stock ? (
                                                    <NeuPill variant="todo">Menipis</NeuPill>
                                                ) : null}
                                            </div>
                                            {part.min_stock > 0 && (
                                                <span className="block text-[11px] text-neu-sub mt-0.5">min {part.min_stock}</span>
                                            )}
                                        </td>
                                        <td data-label="Lokasi">
                                            <span className="text-xs break-words">{part.location ?? '—'}</span>
                                            {part.location_code && <span className="block text-[11px] text-neu-sub">Kode {part.location_code}</span>}
                                        </td>
                                        <td data-label="Supplier">
                                            <span className="text-xs break-words">{part.supplier ?? '—'}</span>
                                        </td>
                                        <td data-label="Aksi">
                                            <div className="flex flex-wrap gap-2">
                                                {can.manage && (
                                                    <>
                                                        <NeuButton size="sm" onClick={() => openMove(part, 'in')}>Masuk</NeuButton>
                                                        <NeuButton size="sm" onClick={() => openMove(part, 'out')}>Keluar</NeuButton>
                                                    </>
                                                )}
                                                <NeuButton size="sm" variant="ghost" onClick={() => openHistory(part)}>Riwayat</NeuButton>
                                                {can.manage && (
                                                    <>
                                                        <NeuButton size="sm" variant="ghost" onClick={() => openEdit(part)}>Ubah</NeuButton>
                                                        <NeuButton size="sm" variant="ghost" onClick={() => setConfirmingId(part.id)}>Hapus</NeuButton>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {/* Form tambah/ubah */}
            <Modal show={formOpen} onClose={() => setFormOpen(false)} maxWidth="2xl">
                <form onSubmit={submitForm} className="p-5 max-h-[85vh] overflow-y-auto">
                    <h3 className="text-lg font-bold mb-4">{editingId ? 'Ubah Suku Cadang' : 'Tambah Suku Cadang'}</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Field label="Kode (Code.asset)" error={form.errors.code}>
                            <input className={FIELD_CLASS} value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} required />
                        </Field>
                        <Field label="Kategori Device" error={form.errors.category}>
                            <input className={FIELD_CLASS} list="spare-categories" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)} />
                        </Field>
                        <Field label="Nama Suku Cadang" className="sm:col-span-2" error={form.errors.name}>
                            <input className={FIELD_CLASS} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                        </Field>
                        <Field label="Merek" error={form.errors.brand}>
                            <input className={FIELD_CLASS} value={form.data.brand} onChange={(e) => form.setData('brand', e.target.value)} />
                        </Field>
                        <Field label="Type" error={form.errors.type}>
                            <input className={FIELD_CLASS} value={form.data.type} onChange={(e) => form.setData('type', e.target.value)} />
                        </Field>
                        <Field label="Keterangan (Desc)" className="sm:col-span-2" error={form.errors.description}>
                            <textarea className={FIELD_CLASS} rows={2} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                        </Field>
                        <Field label="Satuan (Unit)" error={form.errors.unit}>
                            <input className={FIELD_CLASS} value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)} placeholder="pcs" />
                        </Field>
                        <Field label="QTY per unit" error={form.errors.qty}>
                            <input type="number" min="0" className={FIELD_CLASS} value={form.data.qty} onChange={(e) => form.setData('qty', e.target.value)} />
                        </Field>
                        <Field label="Stok saat ini" error={form.errors.stock}>
                            <input type="number" className={FIELD_CLASS} value={form.data.stock} onChange={(e) => form.setData('stock', e.target.value)} />
                        </Field>
                        <Field label="Stok awal" error={form.errors.stock_start}>
                            <input type="number" className={FIELD_CLASS} value={form.data.stock_start} onChange={(e) => form.setData('stock_start', e.target.value)} />
                        </Field>
                        <Field label="Stok minimum" error={form.errors.min_stock}>
                            <input type="number" min="0" className={FIELD_CLASS} value={form.data.min_stock} onChange={(e) => form.setData('min_stock', e.target.value)} />
                        </Field>
                        <Field label="Supplier" error={form.errors.supplier}>
                            <input className={FIELD_CLASS} value={form.data.supplier} onChange={(e) => form.setData('supplier', e.target.value)} />
                        </Field>
                        <Field label="Kebutuhan Untuk" className="sm:col-span-2" error={form.errors.needed_for}>
                            <input className={FIELD_CLASS} value={form.data.needed_for} onChange={(e) => form.setData('needed_for', e.target.value)} />
                        </Field>
                        <Field label="Kode Lokasi (Code.loc)" error={form.errors.location_code}>
                            <input className={FIELD_CLASS} value={form.data.location_code} onChange={(e) => form.setData('location_code', e.target.value)} />
                        </Field>
                        <Field label="Lokasi (LOC)" error={form.errors.location}>
                            <input className={FIELD_CLASS} value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} />
                        </Field>
                        <Field label="Penjelasan" className="sm:col-span-2" error={form.errors.note}>
                            <textarea className={FIELD_CLASS} rows={2} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                        </Field>
                    </div>
                    <datalist id="spare-categories">
                        {categories.map((c) => <option key={c} value={c} />)}
                    </datalist>
                    <div className="flex justify-end gap-2 mt-5">
                        <NeuButton type="button" variant="ghost" onClick={() => setFormOpen(false)}>Batal</NeuButton>
                        <NeuButton type="submit" variant="primary" disabled={form.processing}>
                            {form.processing ? 'Menyimpan…' : 'Simpan'}
                        </NeuButton>
                    </div>
                </form>
            </Modal>

            {/* Transaksi stok */}
            <Modal show={movePart !== null} onClose={() => setMovePart(null)} maxWidth="md">
                {movePart && (
                    <form onSubmit={submitMove} className="p-5">
                        <h3 className="text-lg font-bold mb-1">Barang {moveForm.data.type === 'in' ? 'Masuk' : 'Keluar'}</h3>
                        <p className="text-sm text-neu-sub mb-4">
                            {movePart.code} — {movePart.name}. Stok kini <b>{movePart.stock}</b> {movePart.unit ?? ''}.
                        </p>
                        <div className="flex gap-2 mb-3">
                            <NeuChip active={moveForm.data.type === 'in'} onClick={() => moveForm.setData('type', 'in')}>Masuk</NeuChip>
                            <NeuChip active={moveForm.data.type === 'out'} onClick={() => moveForm.setData('type', 'out')}>Keluar</NeuChip>
                        </div>
                        <Field label="Jumlah" error={moveForm.errors.qty}>
                            <input type="number" min="1" className={FIELD_CLASS} value={moveForm.data.qty} onChange={(e) => moveForm.setData('qty', e.target.value)} required />
                        </Field>
                        <div className="mt-3">
                            <Field label="Tanggal" error={moveForm.errors.moved_at}>
                                <input type="date" className={FIELD_CLASS} value={moveForm.data.moved_at} onChange={(e) => moveForm.setData('moved_at', e.target.value)} />
                            </Field>
                        </div>
                        <div className="mt-3">
                            <Field label="Catatan" error={moveForm.errors.note}>
                                <input className={FIELD_CLASS} value={moveForm.data.note} onChange={(e) => moveForm.setData('note', e.target.value)} />
                            </Field>
                        </div>
                        <div className="flex justify-end gap-2 mt-5">
                            <NeuButton type="button" variant="ghost" onClick={() => setMovePart(null)}>Batal</NeuButton>
                            <NeuButton type="submit" variant="primary" disabled={moveForm.processing}>Catat</NeuButton>
                        </div>
                    </form>
                )}
            </Modal>

            {/* Riwayat transaksi */}
            <Modal show={historyPart !== null} onClose={() => setHistoryPart(null)} maxWidth="lg">
                {historyPart && (
                    <div className="p-5 max-h-[85vh] overflow-y-auto">
                        <h3 className="text-lg font-bold mb-1">Riwayat Stok</h3>
                        <p className="text-sm text-neu-sub mb-4">{historyPart.code} — {historyPart.name}</p>
                        {historyLoading ? (
                            <p className="text-sm text-neu-sub py-4 text-center">Memuat…</p>
                        ) : historyRows.length === 0 ? (
                            <p className="text-sm text-neu-sub py-4 text-center">Belum ada transaksi stok.</p>
                        ) : (
                            <div className="space-y-2">
                                {historyRows.map((m) => (
                                    <div key={m.id} className="flex flex-wrap items-center justify-between gap-2 border-b border-neu-sub/15 pb-2">
                                        <div className="flex items-center gap-2">
                                            <NeuPill variant={m.type === 'in' ? 'done' : 'issue'}>{m.type_label}</NeuPill>
                                            <b className="text-sm">{m.type === 'in' ? '+' : '−'}{m.qty}</b>
                                            <span className="text-xs text-neu-sub">{m.moved_at}</span>
                                        </div>
                                        <div className="text-right">
                                            {m.note && <span className="block text-xs text-neu-sub">{m.note}</span>}
                                            {m.user_name && <span className="block text-[11px] text-neu-sub">oleh {m.user_name}</span>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                        <div className="flex justify-end mt-5">
                            <NeuButton type="button" variant="ghost" onClick={() => setHistoryPart(null)}>Tutup</NeuButton>
                        </div>
                    </div>
                )}
            </Modal>

            {/* Konfirmasi hapus */}
            <Modal show={confirmingId !== null} onClose={() => setConfirmingId(null)} maxWidth="md">
                <div className="p-5">
                    <h3 className="text-lg font-bold mb-2">Hapus suku cadang?</h3>
                    <p className="text-sm text-neu-sub mb-4">
                        Suku cadang akan dihapus dari daftar (bisa dipulihkan lewat impor ulang CSV). Riwayat transaksi stoknya ikut terhapus.
                    </p>
                    <div className="flex justify-end gap-2">
                        <NeuButton type="button" variant="ghost" onClick={() => setConfirmingId(null)}>Batal</NeuButton>
                        <NeuButton type="button" variant="bad" onClick={() => confirmDelete(confirmingId)}>Hapus</NeuButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
