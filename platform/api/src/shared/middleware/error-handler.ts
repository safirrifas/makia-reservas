import type { Context } from 'hono';
import { HTTPException } from 'hono/http-exception';
import { ZodError } from 'zod';

export function errorHandler(err: Error, c: Context) {
  console.error('[API Error]', err);

  // Zod validation errors
  if (err instanceof ZodError) {
    return c.json({
      success: false,
      error: {
        code: 'VALIDATION_ERROR',
        message: 'Invalid request data',
        details: err.errors.map((e) => ({
          field: e.path.join('.'),
          message: e.message,
        })),
      },
    }, 400);
  }

  // HTTP exceptions
  if (err instanceof HTTPException) {
    return c.json({
      success: false,
      error: {
        code: err.message.toUpperCase().replace(/\s+/g, '_'),
        message: err.message,
      },
    }, err.status);
  }

  // Prisma errors
  if (err.constructor.name === 'PrismaClientKnownRequestError') {
    const prismaError = err as { code: string; meta?: { target?: string[] } };

    if (prismaError.code === 'P2002') {
      return c.json({
        success: false,
        error: {
          code: 'DUPLICATE_ENTRY',
          message: `A record with this ${prismaError.meta?.target?.join(', ') || 'value'} already exists`,
        },
      }, 409);
    }

    if (prismaError.code === 'P2025') {
      return c.json({
        success: false,
        error: {
          code: 'NOT_FOUND',
          message: 'Record not found',
        },
      }, 404);
    }
  }

  // Generic server error
  return c.json({
    success: false,
    error: {
      code: 'INTERNAL_SERVER_ERROR',
      message: process.env.NODE_ENV === 'production'
        ? 'An unexpected error occurred'
        : err.message,
    },
  }, 500);
}
