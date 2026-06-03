import { Head, Link, router } from '@inertiajs/react';
import StoreHeader from '@/Components/StoreHeader';

const PAID = ['PAID', 'SUCCESS', 'COMPLETED'];
const FAILED = ['FAILED', 'CANCELLED', 'EXPIRED'];

function badge(status) {
    const s = (status || '').toUpperCase();
    if (PAID.includes(s)) return 'bg-green-100 text-green-700';
    if (FAILED.includes(s)) return 'bg-red-100 text-red-700';
    return 'bg-yellow-100 text-yellow-700';
}

export default function Orders({ orders }) {
    return (
        <div className="min-h-screen bg-gray-50 text-gray-900">
            <Head title="My Orders" />
            <StoreHeader />

            <main className="mx-auto max-w-3xl px-6 py-10">
                <h2 className="mb-6 text-lg font-semibold">My Orders</h2>

                {orders.length === 0 ? (
                    <p className="text-gray-600">
                        No orders yet.{' '}
                        <button onClick={() => router.visit('/')} className="font-semibold underline">
                            Start shopping
                        </button>
                    </p>
                ) : (
                    <ul className="divide-y rounded-lg bg-white shadow-sm">
                        {orders.map((o) => (
                            <li key={o.out_trade_no} className="flex items-center justify-between p-4">
                                <div>
                                    <Link
                                        href={`/order/${o.out_trade_no}`}
                                        className="font-medium hover:underline"
                                    >
                                        {o.out_trade_no}
                                    </Link>
                                    <div className="text-xs text-gray-500">
                                        {new Date(o.created_at).toLocaleString()} · {o.method.toUpperCase()}
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="font-semibold">
                                        ${Number(o.total_amount).toFixed(2)} {o.currency}
                                    </span>
                                    <span className={`rounded-full px-3 py-1 text-xs font-semibold ${badge(o.status)}`}>
                                        {o.status}
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </main>
        </div>
    );
}
