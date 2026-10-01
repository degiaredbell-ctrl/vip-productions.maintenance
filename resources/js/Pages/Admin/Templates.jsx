import { useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';

export default function AdminTemplates({ auth, templates, types }) {
    const { data, setData, post, processing } = useForm({
        machine_type: 'generic',
        name: '',
        items: [{ category: 'Listrik', name: '', spec: '' }],
    });

    const addItem = () => {
        setData('items', [...data.items, { category: 'Listrik', name: '', spec: '' }]);
    };

    const updateItem = (index, field, value) => {
        const newItems = [...data.items];
        newItems[index][field] = value;
        setData('items', newItems);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('admin.templates.store'), { preserveScroll: true });
    };

    const typeLabels = Object.fromEntries(types.map(t => [t.value, t.label]));

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="max-w-3xl mx-auto">
                <div className="mb-5">
                    <h1 className="text-xl font-bold">Template Checklist</h1>
                    <p className="text-sm text-neu-sub">{templates.length} template terdaftar</p>
                </div>

                {/* Add form */}
                <NeuCard className="mb-5">
                    <h2 className="font-bold text-sm mb-3">Tambah Template</h2>
                    <form onSubmit={handleSubmit}>
                        <div className="grid grid-cols-2 gap-3 mb-3">
                            <select
                                value={data.machine_type}
                                onChange={(e) => setData('machine_type', e.target.value)}
                                className="neu-input"
                            >
                                {types.map((t) => (
                                    <option key={t.value} value={t.value}>{t.label}</option>
                                ))}
                            </select>
                            <input
                                type="text"
                                placeholder="Nama template"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="neu-input"
                                required
                            />
                        </div>

                        <div className="space-y-2 mb-3">
                            {data.items.map((item, i) => (
                                <div key={i} className="grid grid-cols-[1fr_1.5fr_1fr] gap-2">
                                    <input
                                        type="text"
                                        placeholder="Kategori"
                                        value={item.category}
                                        onChange={(e) => updateItem(i, 'category', e.target.value)}
                                        className="neu-input !min-h-[40px] !py-2 text-xs"
                                    />
                                    <input
                                        type="text"
                                        placeholder="Nama item"
                                        value={item.name}
                                        onChange={(e) => updateItem(i, 'name', e.target.value)}
                                        className="neu-input !min-h-[40px] !py-2 text-xs"
                                    />
                                    <input
                                        type="text"
                                        placeholder="Spesifikasi"
                                        value={item.spec}
                                        onChange={(e) => updateItem(i, 'spec', e.target.value)}
                                        className="neu-input !min-h-[40px] !py-2 text-xs"
                                    />
                                </div>
                            ))}
                        </div>

                        <div className="flex gap-2">
                            <NeuButton type="button" onClick={addItem} className="!min-h-[40px]">
                                + Item
                            </NeuButton>
                            <NeuButton type="submit" variant="primary" disabled={processing}>
                                {processing ? 'Menyimpan...' : 'Simpan Template'}
                            </NeuButton>
                        </div>
                    </form>
                </NeuCard>

                {/* Template list */}
                <div className="space-y-3">
                    {templates.map((template) => (
                        <NeuCard key={template.id} className="!p-3.5">
                            <div className="flex items-center justify-between mb-2">
                                <div>
                                    <b className="text-sm">{template.name}</b>
                                    <span className="text-xs text-neu-sub ml-2">
                                        {typeLabels[template.machine_type] || template.machine_type}
                                    </span>
                                </div>
                                {template.is_default && (
                                    <NeuPill variant="done">Default</NeuPill>
                                )}
                            </div>
                            <div className="text-xs text-neu-sub">
                                {template.items.length} item · {template.items.map(i => i.category).filter((v, i, a) => a.indexOf(v) === i).join(', ')}
                            </div>
                        </NeuCard>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
