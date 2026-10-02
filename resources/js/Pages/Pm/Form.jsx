import { useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuChip from '@/Components/NeuChip';
import NeuButton from '@/Components/NeuButton';
import NeuTrack from '@/Components/NeuTrack';
import { useState } from 'react';

export default function PmForm({ auth, machine, items, period, year, dashboardUrl, existing, isFuturePeriod, canFill }) {
    const [formItems, setFormItems] = useState(items.map(item => ({ ...item })));
    const [error, setError] = useState('');
    const [submitError, setSubmitError] = useState('');

    const { data, setData, post, processing } = useForm({
        machine_id: machine.id,
        year: year,
        period: period,
        general_note: existing?.general_note || '',
        revision_reason: '',
        items: formItems,
    });

    const updateItem = (index, field, value) => {
        const newItems = [...formItems];
        newItems[index] = { ...newItems[index], [field]: value };
        setFormItems(newItems);
        setData('items', newItems);
    };

    const toggleAction = (index, action) => {
        const newItems = [...formItems];
        newItems[index] = { ...newItems[index], [action]: !newItems[index][action] };
        setFormItems(newItems);
        setData('items', newItems);
    };

    const progress = Math.round(formItems.filter(item => item.actual).length / formItems.length * 100);

    const handleSubmit = (e) => {
        e.preventDefault();
        const incomplete = formItems.some(item => !item.actual);
        if (incomplete) {
            setError('Lengkapi semua nilai Aktual');
            return;
        }
        setError('');
        setSubmitError('');
        post(route('machines.pm.store', { machine: machine.id }), {
            preserveScroll: true,
            onError: (formErrors) => {
                setSubmitError(Object.values(formErrors)[0] ?? 'Gagal menyimpan checklist.');
            },
        });
    };

    const actions = [
        { key: 'act_clean', label: 'Bersihkan' },
        { key: 'act_repair', label: 'Perbaiki' },
        { key: 'act_lubricate', label: 'Lumasi' },
        { key: 'act_replace', label: 'Ganti' },
    ];

    const readOnly = !canFill || isFuturePeriod;

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="max-w-3xl mx-auto">
                {/* Header */}
                <div className="flex items-center gap-3 mb-4">
                    <NeuButton href={dashboardUrl ?? route('dashboard')} className="!px-3">
                        ‹ Kembali
                    </NeuButton>
                    <div>
                        <h1 className="text-lg font-bold">{machine.name}</h1>
                        <p className="text-xs text-neu-sub">
                            {machine.code}
                            {machine.sub_category && ` · ${machine.sub_category}`}
                            {machine.location && ` · ${machine.location}`}
                            {` · Minggu ${machine.week_group}`}
                        </p>
                        <p className="text-xs text-neu-sub">Periode {period} {year}</p>
                    </div>
                </div>

                {/* Progress */}
                <NeuCard className="mb-4">
                    <NeuTrack percentage={progress} />
                    <p className="text-xs text-neu-sub mt-2 text-center">{progress}% selesai</p>
                </NeuCard>

                {readOnly && (
                    <NeuCard className="mb-4 !shadow-neu-in">
                        <p className="text-sm text-neu-sub">
                            {isFuturePeriod ? 'Periode ini belum tiba.' : 'Anda tidak memiliki izin mengisi checklist.'}
                        </p>
                    </NeuCard>
                )}

                {/* Items */}
                <form onSubmit={handleSubmit}>
                    {formItems.map((item, index) => {
                        const showCategory = index === 0 || formItems[index - 1].category !== item.category;
                        return (
                            <div key={index}>
                                {showCategory && (
                                    <div className="text-[13px] font-bold text-neu-sub mt-5 mb-3 px-1">
                                        {item.category}
                                    </div>
                                )}
                                <NeuCard className="mb-3">
                                    <div className="flex items-center justify-between gap-2 mb-3">
                                        <b className="text-sm">{item.item_name}</b>
                                        {item.spec && (
                                            <span className="neu-inset px-2.5 py-1 text-[11px] text-neu-sub flex-none">
                                                {item.spec}
                                            </span>
                                        )}
                                    </div>
                                    <label htmlFor={`actual-${index}`} className="block text-xs text-neu-sub mb-1.5">
                                        Aktual *
                                    </label>
                                    <input
                                        id={`actual-${index}`}
                                        type="text"
                                        value={item.actual}
                                        onChange={(e) => updateItem(index, 'actual', e.target.value)}
                                        placeholder="Nilai aktual"
                                        className={`neu-input ${error && !item.actual ? '!shadow-[inset_4px_4px_9px_#C3CAD6,inset_-4px_-4px_9px_#FFFFFF,0_0_0_2px_#B93A2E]' : ''}`}
                                        disabled={readOnly}
                                    />
                                    <div className="flex gap-2.5 flex-wrap mt-3.5">
                                        {actions.map((action) => (
                                            <NeuChip
                                                key={action.key}
                                                active={item[action.key]}
                                                onClick={() => !readOnly && toggleAction(index, action.key)}
                                                disabled={readOnly}
                                            >
                                                {action.label}
                                            </NeuChip>
                                        ))}
                                    </div>
                                </NeuCard>
                            </div>
                        );
                    })}

                    {/* General note */}
                    {!readOnly && (
                        <NeuCard className="mb-4">
                            <label htmlFor="general_note" className="block text-xs text-neu-sub mb-1.5">
                                Catatan Umum
                            </label>
                            <textarea
                                id="general_note"
                                value={data.general_note}
                                onChange={(e) => setData('general_note', e.target.value)}
                                placeholder="Catatan tambahan (opsional)"
                                className="neu-input min-h-[80px] resize-y"
                                rows={3}
                            />
                        </NeuCard>
                    )}

                    {/* Revision reason */}
                    {!readOnly && existing && (
                        <NeuCard className="mb-4">
                            <label htmlFor="revision_reason" className="block text-xs text-neu-sub mb-1.5">
                                Alasan Revisi
                            </label>
                            <input
                                id="revision_reason"
                                type="text"
                                value={data.revision_reason}
                                onChange={(e) => setData('revision_reason', e.target.value)}
                                placeholder="Jika revisi, jelaskan alasan"
                                className="neu-input"
                            />
                        </NeuCard>
                    )}

                    {(error || submitError) && (
                        <p className="text-sm text-neu-bad font-semibold mb-3 text-center">{error || submitError}</p>
                    )}

                    {!readOnly && (
                        <NeuButton
                            type="submit"
                            variant="primary"
                            className="w-full !py-4 text-base"
                            disabled={processing}
                        >
                            {processing ? 'Menyimpan...' : existing ? 'Simpan Revisi' : 'Simpan Checklist'}
                        </NeuButton>
                    )}
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
