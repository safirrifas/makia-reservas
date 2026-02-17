'use client';
import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';

export function AuthGuard({ children }: { children: React.ReactNode }) {
  const [isAuth, setIsAuth] = useState<boolean | null>(null);
  const router = useRouter();

  useEffect(() => {
    const auth = localStorage.getItem('makia_auth');
    if (auth) {
      try {
        const data = JSON.parse(auth);
        if (data.logged && Date.now() - data.time < 7 * 24 * 60 * 60 * 1000) {
          setIsAuth(true);
          return;
        }
      } catch {}
    }
    router.push('/login');
  }, [router]);

  if (isAuth === null) {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-orange-500"></div>
      </div>
    );
  }

  return <>{children}</>;
}
