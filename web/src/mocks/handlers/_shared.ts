import { HttpResponse, type HttpHandler } from 'msw';

function newRequestId(): string {
  return `mock-${Math.random().toString(36).slice(2, 10)}`;
}

/** Success envelope: `{ code: 200, message, data, request_id }`. */
export function ok<T>(data: T, message = 'ok') {
  return HttpResponse.json({
    code: 200,
    message,
    data,
    request_id: newRequestId(),
  });
}

/**
 * Business failure envelope — HTTP stays 200, `code` carries the outcome,
 * mirroring the real API contract.
 */
export function fail(code: number, message: string, fields?: Record<string, string[]>) {
  return HttpResponse.json({
    code,
    message,
    data: null,
    fields,
    request_id: newRequestId(),
  });
}

// Aggregator flattens every sibling's default export — contribute nothing here.
export default [] satisfies HttpHandler[];
