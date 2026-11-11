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

/** OpenAPI success envelope: `{ data, request_id }` with no code/message. */
export function okOpenapi<T>(data: T) {
  return HttpResponse.json({
    data,
    request_id: newRequestId(),
  });
}

/**
 * OpenAPI failure envelope — the string `code` marks a business error and
 * the HTTP status carries the failure category.
 */
export function failOpenapi(
  status: number,
  code: string,
  message: string,
  fields?: Record<string, string[]>,
) {
  return HttpResponse.json(
    {
      code,
      message,
      fields: fields ?? {},
      request_id: newRequestId(),
    },
    { status },
  );
}

// Aggregator flattens every sibling's default export — contribute nothing here.
export default [] satisfies HttpHandler[];
