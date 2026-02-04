// ============================================
// CONFIG
// ============================================
export interface MakiaClientConfig {
  /** API Key (mk_live_xxx o mk_test_xxx) */
  apiKey?: string;
  /** Access token JWT (alternativa a apiKey) */
  accessToken?: string;
  /** URL base de la API (default: https://api.makia.app/v1) */
  baseUrl?: string;
  /** Organization slug (requerido para algunas operaciones sin auth) */
  organization?: string;
  /** Timeout en ms (default: 30000) */
  timeout?: number;
  /** Headers adicionales */
  headers?: Record<string, string>;
}

// ============================================
// API RESPONSE
// ============================================
export interface ApiResponse<T = unknown> {
  success: boolean;
  data?: T;
  error?: ApiError;
  meta?: PaginationMeta;
}

export interface ApiError {
  code: string;
  message: string;
  details?: unknown;
}

export interface PaginationMeta {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
  hasMore: boolean;
}

// ============================================
// BOOKING
// ============================================
export type BookingStatus = 'PENDING' | 'CONFIRMED' | 'CANCELLED' | 'COMPLETED' | 'NO_SHOW';

export interface Booking {
  id: string;
  customerName: string;
  customerEmail: string;
  customerPhone?: string;
  bookingDate: string;
  bookingTime: string;
  guests: number;
  occasion?: string;
  specialRequests?: string;
  status: BookingStatus;
  source: string;
  editToken?: string;
  metadata: Record<string, unknown>;
  createdAt: string;
  updatedAt: string;
  notes?: BookingNote[];
}

export interface BookingNote {
  id: string;
  content: string;
  createdAt: string;
  user?: { name: string };
}

export interface CreateBookingInput {
  customerName: string;
  customerEmail: string;
  customerPhone?: string;
  /** Fecha en formato YYYY-MM-DD */
  date: string;
  /** Hora en formato HH:MM */
  time: string;
  guests: number;
  occasion?: string;
  specialRequests?: string;
  source?: 'web' | 'widget' | 'api' | 'mobile';
}

export interface UpdateBookingInput {
  customerName?: string;
  customerEmail?: string;
  customerPhone?: string;
  date?: string;
  time?: string;
  guests?: number;
  occasion?: string;
  specialRequests?: string;
  status?: BookingStatus;
}

export interface BookingListParams {
  page?: number;
  limit?: number;
  status?: BookingStatus;
  dateFrom?: string;
  dateTo?: string;
  search?: string;
  sortBy?: 'bookingDate' | 'createdAt' | 'customerName';
  sortOrder?: 'asc' | 'desc';
}

// ============================================
// AVAILABILITY
// ============================================
export interface TimeSlot {
  time: string;
  available: boolean;
  remainingCapacity: number;
}

export interface AvailabilityResponse {
  date: string;
  isOpen: boolean;
  reason?: string;
  slots: TimeSlot[];
}

export interface CheckAvailabilityInput {
  date: string;
  time: string;
  guests: number;
}

export interface CheckAvailabilityResponse {
  available: boolean;
  reason?: string;
  remainingCapacity?: number;
}

// ============================================
// ORGANIZATION
// ============================================
export type Plan = 'FREE' | 'STARTER' | 'PRO' | 'BUSINESS';

export interface Organization {
  id: string;
  name: string;
  slug: string;
  email?: string;
  phone?: string;
  address?: string;
  timezone: string;
  currency: string;
  plan: Plan;
  settings: Record<string, unknown>;
  createdAt: string;
  updatedAt: string;
}

export interface OrganizationStats {
  totalBookings: number;
  monthlyBookings: number;
  pendingBookings: number;
  confirmedBookings: number;
  todayBookings: number;
  plan: {
    name: Plan;
    limit: number;
    used: number;
    remaining: number;
    usagePercentage: number;
  };
}

export interface BusinessHours {
  dayOfWeek: number;
  isOpen: boolean;
  slots: { open: string; close: string }[];
}

export interface SpecialDay {
  id: string;
  date: string;
  type: 'CLOSED' | 'SPECIAL_HOURS' | 'HOLIDAY';
  name?: string;
  slots?: { open: string; close: string }[];
}

// ============================================
// AUTH
// ============================================
export interface LoginInput {
  email: string;
  password: string;
}

export interface RegisterInput {
  email: string;
  password: string;
  name: string;
  organizationName?: string;
}

export interface AuthResponse {
  token: string;
  user: {
    id: string;
    email: string;
    name?: string;
    role: string;
    organization?: {
      id: string;
      name: string;
      slug: string;
      plan?: Plan;
    };
  };
}

export interface ApiKey {
  id: string;
  name: string;
  key?: string; // Solo disponible al crear
  keyPrefix: string;
  scopes: string[];
  lastUsedAt?: string;
  expiresAt?: string;
  createdAt: string;
}
