import { Head, router, usePage } from '@inertiajs/react';
import StoreHeader from '@/Components/StoreHeader';

export default function Index({ products, cartCount }) {
    const { auth } = usePage().props;

    const add = (product) => {
        if (!auth.user) {
            router.visit('/login');
            return;
        }
        router.post('/cart', { product_id: product.id }, { preserveScroll: true });
    };

    return (
        <div className="min-h-screen bg-gray-50 text-gray-900">
            <Head title="Nike Store" />
            <StoreHeader cartCount={cartCount} />

            <main className="mx-auto max-w-5xl px-6 py-10">
                <h2 className="mb-6 text-lg font-semibold">Just Do It.</h2>
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {products.map((p) => (
                        <div key={p.id} className="rounded-xl bg-white p-6 shadow-sm">
                            <div className="mb-4 text-6xl">{p.img}</div>
                            <div className="font-semibold">{p.name}</div>
                            <div className="mb-4 text-gray-600">${p.price.toFixed(2)}</div>
                            <button
                                onClick={() => add(p)}
                                className="w-full rounded-full bg-black py-2 text-sm font-semibold text-white hover:bg-gray-800"
                            >
                                Add to cart
                            </button>
                        </div>
                    ))}
                </div>

                <div className="mt-10 text-center">
                    <button
                        onClick={() => router.visit(auth.user ? '/checkout' : '/login')}
                        className="rounded-full bg-orange-600 px-8 py-3 font-semibold text-white hover:bg-orange-700"
                    >
                        Checkout →
                    </button>
                </div>
            </main>
        </div>
    );
}
