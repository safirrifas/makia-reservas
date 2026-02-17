import type { User, Organization } from '@prisma/client';

export interface Variables {
  user?: User;
  organization?: Organization;
  apiKey?: {
    id: string;
    organizationId: string;
    scopes: string[];
  };
}

export interface JWTPayload {
  sub: string; // user id
  email: string;
  orgId?: string;
  role: string;
  iat: number;
  exp: number;
}

export interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  error?: {
    code: string;
    message: string;
    details?: unknown;
  };
  meta?: {
    page?: number;
    limit?: number;
    total?: number;
    hasMore?: boolean;
  };
}

export interface PaginationParams {
  page?: number;
  limit?: number;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
}

export interface BookingFilters extends PaginationParams {
  status?: string;
  dateFrom?: string;
  dateTo?: string;
  search?: string;
}
