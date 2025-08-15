interface ApiConfig {
  baseURL?: string;
  timeout?: number;
  headers?: Record<string, string>;
}

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  headers?: Record<string, string>;
  body?: any;
  params?: Record<string, string | number | boolean>;
  timeout?: number;
  requireAuth?: boolean;
  useApiKey?: boolean;
}

interface ApiResponse<T = any> {
  data: T;
  status: number;
  statusText: string;
  headers: Headers;
  ok: boolean;
}

interface ApiError {
  message: string;
  status?: number;
  statusText?: string;
  data?: any;
}

class ApiClient {
  private baseURL: string;
  private timeout: number;
  private defaultHeaders: Record<string, string>;
  private authToken: string | null = null;
  private apiKey: string | null = null;

  constructor(config: ApiConfig = {}) {
    this.baseURL = config.baseURL || '/api';
    this.timeout = config.timeout || 10000;
    this.defaultHeaders = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      ...config.headers,
    };
  }

  // Set authentication token for user-based requests
  setAuthToken(token: string | null) {
    this.authToken = token;
  }

  // Set API key for service-based requests
  setApiKey(key: string | null) {
    this.apiKey = key;
  }

  // Get stored auth token
  getAuthToken(): string | null {
    return this.authToken || localStorage.getItem('auth_token');
  }

  // Get stored API key
  getApiKey(): string | null {
    return this.apiKey || localStorage.getItem('api_key');
  }

  // Build URL with query parameters
  private buildURL(
    endpoint: string,
    params?: Record<string, string | number | boolean>
  ): string {
    // Use window.location.origin as the base URL to ensure we have a valid base
    const baseURL = window.location.origin + this.baseURL;

    // Ensure endpoint starts with / if it doesn't already
    const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : '/' + endpoint;

    // Construct the full URL by combining baseURL and endpoint
    const fullURL = baseURL + normalizedEndpoint;
    const url = new URL(fullURL);

    if (params) {
      Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
          url.searchParams.append(key, String(value));
        }
      });
    }

    return url.toString();
  }

  // Create headers for the request
  private createHeaders(options: RequestOptions): Record<string, string> {
    const headers = { ...this.defaultHeaders, ...options.headers };

    if (options.requireAuth && !options.useApiKey) {
      const token = this.getAuthToken();
      if (token) {
        headers['Authorization'] = `Bearer ${token}`;
      }
    }

    if (options.useApiKey) {
      const apiKey = this.getApiKey();
      if (apiKey) {
        headers['X-API-Key'] = apiKey;
      }
    }

    return headers;
  }

  // Create request with timeout
  private async createRequest(
    url: string,
    options: RequestOptions
  ): Promise<Response> {
    const controller = new AbortController();
    const timeoutId = setTimeout(
      () => controller.abort(),
      options.timeout || this.timeout
    );

    try {
      const response = await fetch(url, {
        method: options.method || 'GET',
        headers: this.createHeaders(options),
        body: options.body ? JSON.stringify(options.body) : undefined,
        signal: controller.signal,
      });

      clearTimeout(timeoutId);
      return response;
    } catch (error) {
      clearTimeout(timeoutId);
      if (error instanceof Error && error.name === 'AbortError') {
        throw new Error('Request timeout');
      }
      throw error;
    }
  }

  // Handle response and errors
  private async handleResponse<T>(response: Response): Promise<ApiResponse<T>> {
    const contentType = response.headers.get('content-type');
    let data: T;

    try {
      if (contentType && contentType.includes('application/json')) {
        data = await response.json();
      } else {
        data = (await response.text()) as T;
      }
    } catch (error) {
      data = {} as T;
    }

    if (!response.ok) {
      const error: ApiError = {
        message:
          (data as any)?.message || `HTTP ${response.status}: ${response.statusText}`,
        status: response.status,
        statusText: response.statusText,
        data,
      };

      // Handle specific error cases
      if (response.status === 401) {
        // Clear invalid token
        localStorage.removeItem('auth_token');
        this.setAuthToken(null);
        error.message = 'Authentication required';
      } else if (response.status === 403) {
        error.message = 'Access forbidden';
      } else if (response.status === 404) {
        error.message = 'Resource not found';
      } else if (response.status >= 500) {
        error.message = 'Server error';
      }

      throw error;
    }

    return {
      data,
      status: response.status,
      statusText: response.statusText,
      headers: response.headers,
      ok: response.ok,
    };
  }

  // Generic request method
  async request<T = any>(
    endpoint: string,
    options: RequestOptions = {}
  ): Promise<ApiResponse<T>> {
    try {
      const url = this.buildURL(endpoint, options.params);
      const response = await this.createRequest(url, options);
      return await this.handleResponse<T>(response);
    } catch (error) {
      if (error instanceof Error) {
        throw {
          message: error.message,
          status: 0,
          statusText: 'Network Error',
          data: null,
        } as ApiError;
      }
      throw error;
    }
  }

  // Convenience methods
  async get<T = any>(
    endpoint: string,
    options: Omit<RequestOptions, 'method'> = {}
  ): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, { ...options, method: 'GET' });
  }

  async post<T = any>(
    endpoint: string,
    data?: any,
    options: Omit<RequestOptions, 'method' | 'body'> = {}
  ): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, {
      ...options,
      method: 'POST',
      body: data,
    });
  }

  async put<T = any>(
    endpoint: string,
    data?: any,
    options: Omit<RequestOptions, 'method' | 'body'> = {}
  ): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, { ...options, method: 'PUT', body: data });
  }

  async patch<T = any>(
    endpoint: string,
    data?: any,
    options: Omit<RequestOptions, 'method' | 'body'> = {}
  ): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, {
      ...options,
      method: 'PATCH',
      body: data,
    });
  }

  async delete<T = any>(
    endpoint: string,
    options: Omit<RequestOptions, 'method'> = {}
  ): Promise<ApiResponse<T>> {
    return this.request<T>(endpoint, { ...options, method: 'DELETE' });
  }
}

// Create default API client instance
export const api = new ApiClient();

// Export types for use in other files
export type { ApiConfig, RequestOptions, ApiResponse, ApiError };

// Export the class for custom instances
export { ApiClient };
// test comment
// test comment for commit
