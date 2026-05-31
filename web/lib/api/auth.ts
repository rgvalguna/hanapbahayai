import { apiFetch } from "./client"
import type { AuthUser } from "@/lib/store/auth"

export interface LoginPayload {
  email: string
  password: string
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export interface UpdateUserPayload {
  name?: string
}

export async function login(payload: LoginPayload): Promise<AuthUser> {
  const res = await apiFetch("/v1/auth/login", {
    method: "POST",
    body: JSON.stringify(payload),
  })
  const data = await res.json() as { user: AuthUser }
  return data.user
}

export async function register(payload: RegisterPayload): Promise<AuthUser> {
  const res = await apiFetch("/v1/auth/register", {
    method: "POST",
    body: JSON.stringify(payload),
  })
  const data = await res.json() as { user: AuthUser }
  return data.user
}

export async function logout(): Promise<void> {
  await apiFetch("/v1/auth/logout", { method: "POST" })
}

export async function getMe(): Promise<AuthUser> {
  const res = await apiFetch("/v1/auth/user")
  const data = await res.json() as { user: AuthUser }
  return data.user
}

export async function updateUser(payload: UpdateUserPayload): Promise<AuthUser> {
  const res = await apiFetch("/v1/auth/user", {
    method: "PATCH",
    body: JSON.stringify(payload),
  })
  const data = await res.json() as { user: AuthUser }
  return data.user
}

export async function deleteAccount(): Promise<void> {
  await apiFetch("/v1/auth/account", { method: "DELETE" })
}

export async function sendPasswordResetLink(email: string): Promise<void> {
  await apiFetch("/v1/auth/forgot-password", {
    method: "POST",
    body: JSON.stringify({ email }),
  })
}
