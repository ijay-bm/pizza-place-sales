import axios from 'axios';

/**
 * The client every request to the API goes through. Sanctum authenticates the SPA with the session cookie:
 * withCredentials sends it, and withXSRFToken returns the XSRF-TOKEN cookie as the X-XSRF-TOKEN header.
 */
export const http = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json' }
});
