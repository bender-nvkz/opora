// @opora/api-client — skeleton
// Будет сгенерирован из OpenAPI в Этапе 4
// Пока содержит только типы для /health

export interface HealthResponse {
    status: "ok" | "degraded" | "down";
    version: string;
    uptime: number;
    database: "ok" | "error";
}

export const API_BASE_URL = import.meta.env.VITE_API_URL ?? "http://localhost:8080";
