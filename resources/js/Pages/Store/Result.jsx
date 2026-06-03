import { Head, router } from '@inertiajs/react';
import { useEffect } from 'react';

const PAID = ['PAID', 'SUCCESS', 'COMPLETED'];
const FAILED = ['FAILED', 'CANCELLED', 'EXPIRED'];

export default function Result({ order }) {
    const status = (order.status || '').toUpperCase();
    const paid = PAID.includes(status);
    const failed = FAILED.includes(status);
    const pending = !paid && !failed;

    // Payment-link / KHQR settle async via webhook — poll until terminal.
    useEffect(() => {
        if (!pending) return;
        const id = setInterval(async () => {
            const { data } = await window.axios.get(`/payment/${order.out_trade_no}/status`);
            const s = (data.status || '').toUpperCase();
            if (PAID.includes(s) || FAILED.includes(s)) {
                clearInterval(id);
                router.reload({ only: ['order'] });
            }
        }, 3000);
        return () => clearInterval(id);
    }, [pending, order.out_trade_no]);

    const icon = paid ? '✅' : failed ? '❌' : '⏳';
    const heading = paid
        ? 'Payment successful'
        : failed
            ? 'Payment failed'
            : `Order ${order.status}`;

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-6">
            <Head title="Order result" />
            <div className="w-full max-w-md rounded-xl bg-white p-8 text-center shadow-sm">
                <div className="mb-4 text-6xl">{icon}</div>
                <h1 className="text-xl font-bold">{heading}</h1>
                {pending && (
                    <p className="mt-2 text-xs text-gray-400">Waiting for payment confirmation…</p>
                )}
                <dl className="mt-6 space-y-2 text-left text-sm">
                    <Row label="Reference" value={order.out_trade_no} />
                    <Row label="Amount" value={`$${Number(order.total_amount).toFixed(2)} ${order.currency}`} />
                    <Row label="Method" value={order.method.toUpperCase()} />
                    <Row label="Status" value={order.status} />
                </dl>
                <div className="mt-8 space-y-2">
                    <button
                        onClick={() => router.visit('/orders')}
                        className="w-full rounded-full bg-black py-3 font-semibold text-white"
                    >
                        My Orders
                    </button>
                    <button
                        onClick={() => router.visit('/')}
                        className="w-full rounded-full bg-white py-3 font-semibold text-black ring-1 ring-gray-300"
                    >
                        Back to store
                    </button>
                </div>
            </div>
        </div>
    );
}

function Row({ label, value }) {
    return (
        <div className="flex justify-between border-b py-2">
            <dt className="text-gray-500">{label}</dt>
            <dd className="font-medium">{value}</dd>
        </div>
    );
}
