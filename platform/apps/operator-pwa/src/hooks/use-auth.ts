'use client';

import { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';

interface User {
  id: string;
  email: string;
  name: string;
  role: string;
  organization?: {
    id: string;
    name: string;
  };
}

interface AuthState {
  user: User | null;
  isLoading: boolean;
  isAuthenticated: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
}

const API_URL = process.env.NEXT_PUBLIC_API_URL || 'https://api.contacpro.app/v1';

export function useAuth(): AuthState {
  const [token, setToken] = useState<string | null>(null);

  useEffect(() => {
    // Check for token in localStorage on mount
    const storedToken = localStorage.getItem('makia_token');
    if (storedToken) {
      setToken(storedToken);
    }
  }, []);

  const { data: user, isLoading } = useQuery({
    queryKey: ['auth', 'me', token],
    queryFn: async () => {
      if (!token) return null;

      const response = await fetch(`${API_URL}/auth/me`, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        localStorage.removeItem('makia_token');
        setToken(null);
        return null;
      }

      const data = await response.json();
      return data.data as User;
    },
    enabled: !!token,
    retry: false,
  });

  const login = async (email: string, password: string) => {
    const response = await fetch(`${API_URL}/auth/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ email, password }),
    });

    if (!response.ok) {
      throw new Error('Login failed');
    }

    const data = await response.json();
    const newToken = data.data.token;

    localStorage.setItem('makia_token', newToken);
    setToken(newToken);
  };

  const logout = () => {
    localStorage.removeItem('makia_token');
    setToken(null);
  };

  return {
    user: user || null,
    isLoading: token ? isLoading : false,
    isAuthenticated: !!user,
    login,
    logout,
  };
}
