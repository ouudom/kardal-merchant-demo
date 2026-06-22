import { Head, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import StoreHeader from '@/Components/StoreHeader';

export default function Checkout({ cart }) {
    const currency = 'USD';
    const [tab, setTab] = useState('khqr'); // payment link = primary method
    const total = cart.reduce((sum, i) => sum + Number(i.price) * i.qty, 0);

    const setQty = (item, qty) =>
        router.patch(`/cart/${item.id}`, { qty }, { preserveScroll: true });

    const remove = (item) =>
        router.delete(`/cart/${item.id}`, { preserveScroll: true });

    if (cart.length === 0) {
        return (
            <Shell>
                <p className="text-center text-gray-600">
                    Your cart is empty.{' '}
                    <button onClick={() => router.visit('/')} className="font-semibold underline">
                        Back to store
                    </button>
                </p>
            </Shell>
        );
    }

    return (
        <Shell>
            <Head title="Checkout" />
            <div className="grid gap-8 md:grid-cols-2">
                <div>
                    <h2 className="mb-3 font-semibold">Order summary</h2>
                    <ul className="divide-y rounded-lg bg-white p-4 shadow-sm">
                        {cart.map((item) => (
                            <li key={item.id} className="flex items-center justify-between py-3 text-sm">
                                <span>{item.img} {item.name}</span>
                                <span className="flex items-center gap-3">
                                    <span className="flex items-center gap-1">
                                        <QtyBtn onClick={() => setQty(item, item.qty - 1)}>−</QtyBtn>
                                        <span className="w-6 text-center">{item.qty}</span>
                                        <QtyBtn onClick={() => setQty(item, item.qty + 1)}>+</QtyBtn>
                                    </span>
                                    <span className="w-16 text-right">
                                        ${(Number(item.price) * item.qty).toFixed(2)}
                                    </span>
                                    <button
                                        onClick={() => remove(item)}
                                        aria-label="Remove item"
                                        className="text-gray-400 hover:text-red-600"
                                    >
                                        ✕
                                    </button>
                                </span>
                            </li>
                        ))}
                        <li className="flex justify-between pt-3 font-bold">
                            <span>Total</span>
                            <span>${total.toFixed(2)} {currency}</span>
                        </li>
                    </ul>
                </div>

                <div>
                    <div className="mb-4 flex gap-2">
                        <TabButton active={tab === 'khqr'} onClick={() => setTab('khqr')}>KHQR</TabButton>
                        <TabButton active={tab === 'link'} onClick={() => setTab('link')}>Payment Link</TabButton>
                        {/* Card payment disabled for this demo.
                        <TabButton active={tab === 'card'} onClick={() => setTab('card')}>Card</TabButton>
                        */}
                    </div>
                    {tab === 'link' && <LinkPanel amount={total} />}
                    {tab === 'khqr' && <KhqrPanel amount={total} />}
                    {/* {tab === 'card' && <CardPanel amount={total} />} */}
                </div>
            </div>
        </Shell>
    );
}

function Shell({ children }) {
    return (
        <div className="min-h-screen bg-gray-50 text-gray-900">
            <StoreHeader />
            <main className="mx-auto max-w-4xl px-6 py-10">{children}</main>
        </div>
    );
}

function QtyBtn({ onClick, children }) {
    return (
        <button
            onClick={onClick}
            className="flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-sm font-bold text-gray-700 hover:bg-gray-200"
        >
            {children}
        </button>
    );
}

function TabButton({ active, onClick, children }) {
    return (
        <button
            onClick={onClick}
            className={`rounded-full px-5 py-2 text-sm font-semibold ${active ? 'bg-black text-white' : 'bg-white text-black'}`}
        >
            {children}
        </button>
    );
}

function LinkPanel({ amount }) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const start = async () => {
        setBusy(true);
        setError(null);
        try {
            const { data } = await window.axios.post('/payment/link');
            if (!data.payment_link) {
                throw new Error('Unexpected response from server.');
            }
            // Redirect the buyer to the Kardal-hosted payment page.
            window.location.href = data.payment_link;
        } catch (e) {
            setError(e.response?.data?.message || e.message || 'Failed to create payment link.');
            setBusy(false);
        }
    };

    return (
        <div className="rounded-lg bg-white p-6 text-center shadow-sm">
            <p className="mb-4 text-sm text-gray-600">
                Redirect to the Kardal-hosted page to pay with any enabled method.
            </p>
            <button
                onClick={start}
                disabled={busy}
                className="w-full rounded-full bg-black py-3 font-semibold text-white disabled:opacity-50"
            >
                {busy ? 'Redirecting…' : `Pay $${amount.toFixed(2)} via link`}
            </button>
            {error && <p className="mt-3 text-sm text-red-600">{error}</p>}
        </div>
    );
}

function KhqrPanel({ amount }) {
    const [state, setState] = useState('idle'); // idle | loading | showing | error
    const [qr, setQr] = useState(null);
    const [outTradeNo, setOutTradeNo] = useState(null);
    const [error, setError] = useState(null);
    const poll = useRef(null);

    const start = async () => {
        setState('loading');
        setError(null);
        try {
            const { data } = await window.axios.post('/payment/khqr');
            if (!data.qrcode || !data.out_trade_no) {
                throw new Error('Unexpected response from server.');
            }
            setQr(data.qrcode);
            setOutTradeNo(data.out_trade_no);
            setState('showing');
        } catch (e) {
            setError(e.response?.data?.message || 'Failed to create KHQR.');
            setState('error');
        }
    };

    useEffect(() => {
        if (state !== 'showing' || !outTradeNo) return;
        poll.current = setInterval(async () => {
            const { data } = await window.axios.get(`/payment/${outTradeNo}/status`);
            if (['PAID', 'SUCCESS', 'COMPLETED'].includes((data.status || '').toUpperCase())) {
                clearInterval(poll.current);
                router.visit(`/order/${outTradeNo}`);
            }
        }, 3000);
        return () => clearInterval(poll.current);
    }, [state, outTradeNo]);

    return (
        <div className="rounded-lg bg-white p-6 text-center shadow-sm">
            {state === 'idle' && (
                <button onClick={start} className="w-full rounded-full bg-black py-3 font-semibold text-white">
                    Generate KHQR — ${amount.toFixed(2)}
                </button>
            )}
            {state === 'loading' && <p>Generating QR…</p>}
            {state === 'showing' && qr && (
                <>
                    <img
                        alt="KHQR"
                        className="mx-auto"
                        src={`https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(qr)}`}
                    />
                    <p className="mt-3 text-sm text-gray-600">Scan with any KHQR-supported bank app.</p>
                    <p className="mt-1 text-xs text-gray-400">Waiting for payment…</p>
                </>
            )}
            {state === 'error' && <p className="text-red-600">{error}</p>}
        </div>
    );
}

function Input(props) {
    return <input {...props} className="w-full rounded-md border-gray-300 text-sm focus:border-black focus:ring-black" />;
}

/* Card payment disabled for this demo — kept for reference.
function CardPanel({ amount }) {
    const [form, setForm] = useState({
        number: '', securityCode: '', month: '', year: '', holder_name: '',
        first_name: '', last_name: '', email: '', phone_number: '',
    });
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const set = (k) => (e) => setForm({ ...form, [k]: e.target.value });

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
            const { data } = await window.axios.post('/payment/card', {
                card: {
                    number: form.number, securityCode: form.securityCode,
                    month: form.month, year: form.year, holder_name: form.holder_name,
                },
                customer: {
                    first_name: form.first_name, last_name: form.last_name,
                    email: form.email, phone_number: form.phone_number,
                },
            });

            if (data.html_confirm_payment) {
                const w = window.open('', '_self');
                w.document.write(data.html_confirm_payment);
                w.document.close();
                return;
            }
            if (!data.out_trade_no) {
                throw new Error('Unexpected response from server.');
            }
            router.visit(`/order/${data.out_trade_no}`);
        } catch (err) {
            setError(err.response?.data?.message || 'Card payment failed.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <form onSubmit={submit} className="space-y-3 rounded-lg bg-white p-6 shadow-sm">
            <Input placeholder="Card number" value={form.number} onChange={set('number')} />
            <div className="grid grid-cols-3 gap-2">
                <Input placeholder="MM" value={form.month} onChange={set('month')} />
                <Input placeholder="YY" value={form.year} onChange={set('year')} />
                <Input placeholder="CVV" value={form.securityCode} onChange={set('securityCode')} />
            </div>
            <Input placeholder="Cardholder name" value={form.holder_name} onChange={set('holder_name')} />
            <div className="grid grid-cols-2 gap-2">
                <Input placeholder="First name" value={form.first_name} onChange={set('first_name')} />
                <Input placeholder="Last name" value={form.last_name} onChange={set('last_name')} />
            </div>
            <Input placeholder="Email" value={form.email} onChange={set('email')} />
            <Input placeholder="Phone number" value={form.phone_number} onChange={set('phone_number')} />
            {error && <p className="text-sm text-red-600">{error}</p>}
            <button disabled={busy} className="w-full rounded-full bg-black py-3 font-semibold text-white disabled:opacity-50">
                {busy ? 'Processing…' : `Pay $${amount.toFixed(2)}`}
            </button>
            <p className="text-center text-xs text-gray-400">Card data is RSA-encrypted server-side before reaching Kardal.</p>
        </form>
    );
}
*/
