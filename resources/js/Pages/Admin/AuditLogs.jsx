import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import NeuCard from '@/Components/NeuCard';

export default function AuditLogs({ auth, logs }) {
    return (
        <AuthenticatedLayout user={auth.user}>
            <div className="max-w-3xl mx-auto">
                <div className="mb-5">
                    <h1 className="text-xl font-bold">Audit Log</h1>
                    <p className="text-sm text-neu-sub">{logs.length} catatan terakhir</p>
                </div>

                <div className="space-y-2">
                    {logs.map((log) => (
                        <NeuCard key={log.id} className="!p-3.5">
                            <div className="flex items-center justify-between">
                                <div>
                                    <b className="text-sm font-mono">{log.action}</b>
                                    <span className="text-xs text-neu-sub ml-2">{log.user_name}</span>
                                </div>
                                <span className="text-xs text-neu-sub">{log.created_at}</span>
                            </div>
                            {log.subject_type && (
                                <p className="text-xs text-neu-sub mt-1">
                                    {log.subject_type} #{log.subject_id}
                                </p>
                            )}
                        </NeuCard>
                    ))}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
