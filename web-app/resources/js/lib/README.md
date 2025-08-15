# API Wrapper Documentation

This directory contains a comprehensive API wrapper for handling authenticated requests in the Top Frag application. The wrapper provides a clean, type-safe interface for making HTTP requests with automatic authentication handling.

## Files

- `api.ts` - Core API client class and types
- `useApi.tsx` - React hooks for using the API wrapper
- `api-examples.ts` - Comprehensive usage examples
- `README.md` - This documentation file

## Features

### Core API Client (`api.ts`)

- **Automatic Authentication**: Supports both Bearer token and API key authentication
- **Request/Response Interceptors**: Built-in error handling and response processing
- **TypeScript Support**: Full type safety with generic response types
- **Timeout Handling**: Configurable request timeouts with automatic cancellation
- **Query Parameters**: Easy URL parameter building
- **Error Handling**: Comprehensive error handling with specific error types
- **Flexible Configuration**: Customizable base URL, headers, and timeouts

### React Hooks (`useApi.tsx`)

- **useApi**: Main hook with configurable authentication options
- **useAuthenticatedApi**: Specialized hook for user-authenticated requests
- **useApiKeyApi**: Specialized hook for API key-authenticated requests
- **usePublicApi**: Specialized hook for public requests
- **Automatic Error Handling**: Built-in error handling with logout on 401 errors
- **React Integration**: Seamless integration with React components and authentication context

## Quick Start

### Basic Usage

```typescript
import { api } from './lib/api';

// Make an authenticated request
const response = await api.get('/auth/user', { requireAuth: true });
console.log(response.data);
```

### React Component Usage

```typescript
import { useAuthenticatedApi } from './hooks/useApi';

function MyComponent() {
  const { get, post } = useAuthenticatedApi();
  
  const handleGetUser = async () => {
    try {
      const response = await get('/auth/user');
      console.log(response.data);
    } catch (error) {
      console.error('Failed to get user:', error);
    }
  };
  
  return <button onClick={handleGetUser}>Get User</button>;
}
```

## API Reference

### ApiClient Class

#### Constructor

```typescript
new ApiClient(config?: ApiConfig)
```

**Config Options:**
- `baseURL?: string` - Base URL for all requests (default: '/api')
- `timeout?: number` - Request timeout in milliseconds (default: 10000)
- `headers?: Record<string, string>` - Default headers to include in all requests

#### Methods

##### Authentication

```typescript
setAuthToken(token: string | null): void
setApiKey(key: string | null): void
getAuthToken(): string | null
getApiKey(): string | null
```

##### HTTP Methods

```typescript
get<T>(endpoint: string, options?: RequestOptions): Promise<ApiResponse<T>>
post<T>(endpoint: string, data?: any, options?: RequestOptions): Promise<ApiResponse<T>>
put<T>(endpoint: string, data?: any, options?: RequestOptions): Promise<ApiResponse<T>>
patch<T>(endpoint: string, data?: any, options?: RequestOptions): Promise<ApiResponse<T>>
delete<T>(endpoint: string, options?: RequestOptions): Promise<ApiResponse<T>>
request<T>(endpoint: string, options?: RequestOptions): Promise<ApiResponse<T>>
```

### RequestOptions Interface

```typescript
interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  headers?: Record<string, string>;
  body?: any;
  params?: Record<string, string | number | boolean>;
  timeout?: number;
  requireAuth?: boolean;
  useApiKey?: boolean;
}
```

### ApiResponse Interface

```typescript
interface ApiResponse<T = any> {
  data: T;
  status: number;
  statusText: string;
  headers: Headers;
  ok: boolean;
}
```

### ApiError Interface

```typescript
interface ApiError {
  message: string;
  status?: number;
  statusText?: string;
  data?: any;
}
```

## Authentication Types

### 1. User Authentication (Bearer Token)

Used for user-specific requests that require login.

```typescript
// Set token manually
api.setAuthToken('your-auth-token');

// Make authenticated request
const response = await api.get('/auth/user', { requireAuth: true });
```

### 2. API Key Authentication

Used for service-to-service communication.

```typescript
// Set API key manually
api.setApiKey('your-api-key');

// Make API key request
const response = await api.post('/upload/demo', data, { useApiKey: true });
```

### 3. Public Requests

Used for endpoints that don't require authentication.

```typescript
// Make public request
const response = await api.get('/health', { requireAuth: false });
```

## React Hooks

### useApi Hook

Main hook with configurable authentication options.

```typescript
const { get, post, put, patch, delete: deleteRequest, request, handleError } = useApi(options);
```

**Options:**
- `requireAuth?: boolean` - Whether to include authentication (default: true)
- `useApiKey?: boolean` - Whether to use API key instead of user token (default: false)

### Specialized Hooks

```typescript
// For user authentication
const authApi = useAuthenticatedApi();

// For API key authentication
const apiKeyApi = useApiKeyApi();

// For public requests
const publicApi = usePublicApi();
```

## Error Handling

The API wrapper provides comprehensive error handling:

### Automatic Error Handling

- **401 Unauthorized**: Automatically clears invalid tokens and logs out user
- **403 Forbidden**: Provides clear access denied message
- **404 Not Found**: Provides resource not found message
- **5xx Server Errors**: Provides generic server error message
- **Network Errors**: Handles timeout and connection issues

### Custom Error Handling

```typescript
try {
  const response = await api.get('/protected-endpoint', { requireAuth: true });
  return response.data;
} catch (error: any) {
  if (error.status === 401) {
    // Handle authentication error
    console.log('User needs to login');
  } else if (error.status === 403) {
    // Handle permission error
    console.log('Access denied');
  } else {
    // Handle other errors
    console.log('Error:', error.message);
  }
  throw error;
}
```

## TypeScript Support

The API wrapper provides full TypeScript support with generic types:

```typescript
interface User {
  id: number;
  name: string;
  email: string;
}

interface ApiResponse<T> {
  data: T;
  message?: string;
  status: string;
}

// Typed request
const response = await api.get<ApiResponse<User>>('/auth/user', { requireAuth: true });
const user: User = response.data.data;
console.log(user.name); // TypeScript knows this is a string
```

## Examples

See `api-examples.ts` for comprehensive examples covering:

1. Direct API client usage
2. React hook usage
3. Authentication scenarios
4. Error handling
5. TypeScript integration
6. Custom client instances
7. Query parameters
8. Different HTTP methods

## Migration from Direct Fetch

The API wrapper is designed to be a drop-in replacement for direct fetch calls. Here's how to migrate:

### Before (Direct Fetch)

```typescript
const response = await fetch('/api/auth/user', {
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  },
});

if (!response.ok) {
  throw new Error('Request failed');
}

const data = await response.json();
```

### After (API Wrapper)

```typescript
const response = await api.get('/auth/user', { requireAuth: true });
const data = response.data;
```

## Best Practices

1. **Use TypeScript**: Always define interfaces for your API responses
2. **Handle Errors**: Always wrap API calls in try-catch blocks
3. **Use Appropriate Hooks**: Use specialized hooks for different authentication types
4. **Set Timeouts**: Configure appropriate timeouts for different types of requests
5. **Validate Responses**: Always validate response data before using it
6. **Use Query Parameters**: Use the params option for GET requests with filters

## Configuration

### Environment-Specific Configuration

```typescript
// Development
const devApi = new ApiClient({
  baseURL: 'http://localhost:8000/api',
  timeout: 5000,
});

// Production
const prodApi = new ApiClient({
  baseURL: 'https://api.topfrag.com/api',
  timeout: 15000,
});
```

### Custom Headers

```typescript
const customApi = new ApiClient({
  headers: {
    'X-Custom-Header': 'value',
    'Accept-Language': 'en-US',
  },
});
```

## Troubleshooting

### Common Issues

1. **401 Errors**: Check if the auth token is valid and not expired
2. **CORS Errors**: Ensure the API server allows requests from your domain
3. **Timeout Errors**: Increase the timeout for slow endpoints
4. **Type Errors**: Make sure your TypeScript interfaces match the API response structure

### Debug Mode

Enable debug logging by setting the environment variable:

```typescript
if (process.env.NODE_ENV === 'development') {
  // Add debug logging
  console.log('API Request:', { endpoint, options });
}
```
