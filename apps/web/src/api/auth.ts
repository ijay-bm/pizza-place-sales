import axios from 'axios';
import { http } from './http';

export interface User {
  id: number;
  name: string;
  email: string;
}

export interface Credentials {
  email: string;
  password: string;
}

/**
 * Sets the XSRF-TOKEN cookie, which Sanctum requires before the first request that changes state.
 */
export async function fetchCsrfCookie(): Promise<void> {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

export async function login(credentials: Credentials): Promise<User> {
  const { data } = await http.post<{ data: User }>('/auth/login', credentials);

  return data.data;
}

export async function logout(): Promise<void> {
  await http.post('/auth/logout');
}

export async function fetchUser(): Promise<User> {
  const { data } = await http.get<{ data: User }>('/auth/user');

  return data.data;
}
