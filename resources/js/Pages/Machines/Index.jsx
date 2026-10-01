import { useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';
import NeuButton from '@/Components/NeuButton';
import NeuPill from '@/Components/NeuPill';
import { Link } from '@inertiajs/react';

export default function Machines({ auth, machines, types }) {
    const { data, setData, post, processing } = useForm({
        code: '',
        name: '',
        type: 'generic',
        week_group: 1,
        template_id: '',
        is_active: true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('machines.store'), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="max-w-3xl mx-auto">
                <div className="flex items-center justify-between mb-5">
                    <div>
                        <h1 className="text-xl font-bold">Kelola Mesin</h1>
                        <p className="text-sm text-neu-sub">{machines.length} mesin terdaftar</p>
                    </div>
                </div>

                {/* Add form */}
                <NeuCard className="mb-5">
                    <h2 className="font-bold text-sm mb-3">Tambah Mesin</h2>
                    <form onSubmit={handleSubmit} className="grid grid-cols-2 gap-3">
                        <input
                            type="text"
                            placeholder="Kode (mis. M100)"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value)}
                            className="neu-input"
                            required
                        />
                        <input
                            type="text"
                            placeholder="Nama mesin"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="neu-input"
                            required
                        />
                        <select
                            value={data.type}
                            onChange={(e) => setData('type', e.target.value)}
                            className="neu-input"
                        >
                            {types.map((t) => (
                                <option key={t.value} value={t.value}>{t.label}</option>
                            ))}
                        </select>
                        <select
                            value={data.week_group}
                            onChange={(e) => setData('week_group', parseInt(e.target.value))}
                            className="neu-input"
                        >
                            {[1,2,3,4,5,6,7,8,9].map((w) => (
                                <option key={w} value={w}>Minggu {w}</option>
                            ))}
                        </select>
                        <div className="col-span-2">
                            <NeuButton type="submit" variant="primary" disabled={processing}>
                                {processing ? 'Menambahkan...' : '+ Tambah Mesin'}
                            </NeuButton>
                        </div>
                    </form>
                </NeuCard>

                {/* Machine list */}
                <div className="grid gap-0 lg:grid-cols-2 lg:gap-x-5">
                    {machines.map((machine) => (
                        <div
                            key={machine.id}
                            className="flex items-center gap-3.5 p-3.5 rounded-[20px] shadow-neu-up mb-4"
                        >
                            <span className="neu-inset w-12 h-12 flex-none grid place-items-center font-bold text-sm text-neu-accent">
                                {machine.code}
                            </span>
                            <div className="min-w-0 flex-1">
                                <b className="block text-sm truncate">{machine.name}</b>
                                <span className="text-xs text-neu-sub">
                                    {types.find(t => t.value === machine.type)?.label} · Minggu {machine.week_group}
                                    {machine.template_name && ` · ${machine.template_name}`}
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <NeuPill variant={machine.is_active ? 'done' : 'todo'}>
                                    {machine.is_active ? 'Aktif' : 'Nonaktif'}
                                </NeuPill>
                                <Link
                                    href={route('machines.history', { machine: machine.id })}
                                    className="neu-btn !min-h-[36px] !px-3 !py-1.5 text-xs"
                                >
                                    Riwayat
                                </Link>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
