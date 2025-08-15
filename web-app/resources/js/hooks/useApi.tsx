import { useCallback, useMemo } from 'react';
import { useAuth } from './useAuth';
import { api, ApiClient, type RequestOptions, type ApiResponse, type ApiError } from '../lib/api';

interface UseApiOptions {
  requireAuth?: boolean;
  useApiKey?: boolean;
}

interface UseApiReturn {
  // API client instance
  client: ApiClient;
  
  // Convenience methods with authentication
  get: <T = any>(endpoint: string, options?: Omit<RequestOptions, 'method'>) => Promise<ApiResponse<T>>;
  post: <T = any>(endpoint: string, data?: any, options?: Omit<RequestOptions, 'method' | 'body'>) => Promise<ApiResponse<T>>;
  put: <T = any>(endpoint: string, data?: any, options?: Omit<RequestOptions, 'method' | 'body'>) => Promise<ApiResponse<T>>;
  patch: <T = any>(endpoint: string, data?: any, options?: Omit<RequestOptions, 'method' | 'body'>) => Promise<ApiResponse<T>>;
  delete: <T = any>(endpoint: string, options?: Omit<RequestOptions, 'method'>) => Promise<ApiResponse<T>>;
  
  // Generic request method
  request: <T = any>(endpoint: string, options?: RequestOptions) => Promise<ApiResponse<T>>;
  
  // Error handling utilities
  handleError: (error: ApiError) => void;
}

export const useApi = (options: UseApiOptions = {}): UseApiReturn => {
  const { token, logout } = useAuth();
  const { requireAuth = true, useApiKey = false } = options;

  // Update API client with current authentication state
  const client = useMemo(() => {
    if (requireAuth && !useApiKey) {
      api.setAuthToken(token);
    }
    return api;
  }, [token, requireAuth, useApiKey]);

  // Default error handler
  const handleError = useCallback((error: ApiError) => {
    console.error('API Error:', error);
    
    // Handle authentication errors
    if (error.status === 401) {
      logout();
    }
    
    // You can add more error handling logic here
    // For example, showing toast notifications, etc.
  }, [logout]);

  // Wrapper functions that automatically include authentication
  const get = useCallback(async <T = any>(
    endpoint: string, 
    requestOptions: Omit<RequestOptions, 'method'> = {}
  ): Promise<ApiResponse<T>> => {
    try {
      return await client.get<T>(endpoint, {
        ...requestOptions,
        requireAuth,
        useApiKey,
      });
    } catch (error) {
      handleError(error as ApiError);
      throw error;
    }
  }, [client, requireAuth, useApiKey, handleError]);

  const post = useCallback(async <T = any>(
    endpoint: string, 
    data?: any, 
    requestOptions: Omit<RequestOptions, 'method' | 'body'> = {}
  ): Promise<ApiResponse<T>> => {
    try {
      return await client.post<T>(endpoint, data, {
        ...requestOptions,
        requireAuth,
        useApiKey,
      });
    } catch (error) {
      handleError(error as ApiError);
      throw error;
    }
  }, [client, requireAuth, useApiKey, handleError]);

  const put = useCallback(async <T = any>(
    endpoint: string, 
    data?: any, 
    requestOptions: Omit<RequestOptions, 'method' | 'body'> = {}
  ): Promise<ApiResponse<T>> => {
    try {
      return await client.put<T>(endpoint, data, {
        ...requestOptions,
        requireAuth,
        useApiKey,
      });
    } catch (error) {
      handleError(error as ApiError);
      throw error;
    }
  }, [client, requireAuth, useApiKey, handleError]);

  const patch = useCallback(async <T = any>(
    endpoint: string, 
    data?: any, 
    requestOptions: Omit<RequestOptions, 'method' | 'body'> = {}
  ): Promise<ApiResponse<T>> => {
    try {
      return await client.patch<T>(endpoint, data, {
        ...requestOptions,
        requireAuth,
        useApiKey,
      });
    } catch (error) {
      handleError(error as ApiError);
      throw error;
    }
  }, [client, requireAuth, useApiKey, handleError]);

  const deleteRequest = useCallback(async <T = any>(
    endpoint: string, 
    requestOptions: Omit<RequestOptions, 'method'> = {}
  ): Promise<ApiResponse<T>> => {
    try {
      return await client.delete<T>(endpoint, {
        ...requestOptions,
        requireAuth,
        useApiKey,
      });
    } catch (error) {
      handleError(error as ApiError);
      throw error;
    }
  }, [client, requireAuth, useApiKey, handleError]);

  const request = useCallback(async <T = any>(
    endpoint: string, 
    requestOptions: RequestOptions = {}
  ): Promise<ApiResponse<T>> => {
    try {
      return await client.request<T>(endpoint, {
        ...requestOptions,
        requireAuth,
        useApiKey,
      });
    } catch (error) {
      handleError(error as ApiError);
      throw error;
    }
  }, [client, requireAuth, useApiKey, handleError]);

  return {
    client,
    get,
    post,
    put,
    patch,
    delete: deleteRequest,
    request,
    handleError,
  };
};

// Specialized hooks for different use cases
export const useAuthenticatedApi = () => useApi({ requireAuth: true, useApiKey: false });
export const useApiKeyApi = () => useApi({ requireAuth: false, useApiKey: true });
export const usePublicApi = () => useApi({ requireAuth: false, useApiKey: false });
