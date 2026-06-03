import { Link, router, usePage } from '@inertiajs/react';

export default function StoreHeader({ cartCount = null }) {
    const { auth } = usePage().props;

    return (
        <header className="flex items-center justify-between bg-black px-8 py-5 text-white">
            <Link href="/" className="text-2xl font-bold tracking-tight">
                NIKE STORE
            </Link>

            <nav className="flex items-center gap-3 text-sm font-semibold">
                {auth.user ? (
                    <>
                        <Link href="/orders" className="hover:text-gray-300">My Orders</Link>
                        {cartCount !== null && (
                            <Link href="/checkout" className="rounded-full bg-white px-5 py-2 text-black">
                                Cart ({cartCount})
                            </Link>
                        )}
                        <button
                            onClick={() => router.post('/logout')}
                            className="hover:text-gray-300"
                        >
                            Logout
                        </button>
                    </>
                ) : (
                    <>
                        <Link href="/login" className="hover:text-gray-300">Log in</Link>
                        <Link href="/register" className="rounded-full bg-white px-5 py-2 text-black">
                            Register
                        </Link>
                    </>
                )}
            </nav>
        </header>
    );
}
