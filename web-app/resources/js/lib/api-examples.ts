// Examples of how to use the API wrapper
import { api } from './api';
import { useApi, useAuthenticatedApi, useApiKeyApi, usePublicApi } from '../hooks/useApi';

// ============================================================================
// Direct API Client Usage (outside of React components)
// ============================================================================

// Example 1: Making authenticated requests
export const exampleAuthenticatedRequests = async () => {
    try {
        // Set the auth token (usually done automatically by useAuth)
        api.setAuthToken('your-auth-token');

        // Get user profile
        const userResponse = await api.get('/auth/user', { requireAuth: true });
        console.log('User:', userResponse.data);

        // Update user profile
        const updateResponse = await api.put('/auth/user', {
            name: 'New Name',
            email: 'newemail@example.com'
        }, { requireAuth: true });
        console.log('Updated:', updateResponse.data);

    } catch (error) {
        console.error('Error:', error);
    }
};

// Example 2: Making API key requests
export const exampleApiKeyRequests = async () => {
    try {
        // Set the API key
        api.setApiKey('your-api-key');

        // Upload demo file
        const uploadResponse = await api.post('/upload/demo', {
            file: 'demo-file.dem',
            metadata: { map: 'de_dust2', players: 10 }
        }, { useApiKey: true });
        console.log('Upload result:', uploadResponse.data);

    } catch (error) {
        console.error('Error:', error);
    }
};

// Example 3: Making public requests
export const examplePublicRequests = async () => {
    try {
        // Health check (no authentication required)
        const healthResponse = await api.get('/health', { requireAuth: false });
        console.log('Health status:', healthResponse.data);

    } catch (error) {
        console.error('Error:', error);
    }
};

// Example 4: Using query parameters
export const exampleWithQueryParams = async () => {
    try {
        // Get matches with pagination and filters
        const matchesResponse = await api.get('/matches', {
            requireAuth: true,
            params: {
                page: 1,
                per_page: 20,
                map: 'de_dust2',
                date_from: '2024-01-01',
                date_to: '2024-12-31'
            }
        });
        console.log('Matches:', matchesResponse.data);

    } catch (error) {
        console.error('Error:', error);
    }
};

// Example 5: Error handling
export const exampleErrorHandling = async () => {
    try {
        const response = await api.get('/protected-endpoint', { requireAuth: true });
        return response.data;
    } catch (error: any) {
        if (error.status === 401) {
            console.log('User needs to login');
            // Redirect to login page
        } else if (error.status === 403) {
            console.log('User does not have permission');
            // Show access denied message
        } else if (error.status === 404) {
            console.log('Resource not found');
            // Show 404 page
        } else if (error.status >= 500) {
            console.log('Server error');
            // Show server error message
        } else {
            console.log('Other error:', error.message);
        }
        throw error;
    }
};

// ============================================================================
// React Hook Usage Examples
// ============================================================================

// Example 6: Using the useApi hook in a React component
export const ExampleComponent = () => {
    // For authenticated requests (default)
    const { get, post, put, delete: deleteRequest } = useApi();

    // For API key requests
    const { post: postWithApiKey } = useApi({ useApiKey: true });

    // For public requests
    const { get: getPublic } = useApi({ requireAuth: false });

    const handleGetUser = async () => {
        try {
            const response = await get('/auth/user');
            console.log('User data:', response.data);
        } catch (error) {
            console.error('Failed to get user:', error);
        }
    };

    const handleUploadDemo = async (fileData: any) => {
        try {
            const response = await postWithApiKey('/upload/demo', fileData);
            console.log('Upload successful:', response.data);
        } catch (error) {
            console.error('Upload failed:', error);
        }
    };

    const handleHealthCheck = async () => {
        try {
            const response = await getPublic('/health');
            console.log('Health status:', response.data);
        } catch (error) {
            console.error('Health check failed:', error);
        }
    };

    return (
        <div>
        <button onClick= { handleGetUser } > Get User </button>
            < button onClick = {() => handleUploadDemo({ file: 'demo.dem' })}> Upload Demo </button>
                < button onClick = { handleHealthCheck } > Health Check </button>
                    </div>
  );
};

// Example 7: Using specialized hooks
export const SpecializedHooksExample = () => {
    // For user authentication
    const authApi = useAuthenticatedApi();

    // For API key authentication
    const apiKeyApi = useApiKeyApi();

    // For public endpoints
    const publicApi = usePublicApi();

    const handleUserAction = async () => {
        try {
            const response = await authApi.get('/user/profile');
            console.log('User profile:', response.data);
        } catch (error) {
            console.error('Failed to get profile:', error);
        }
    };

    const handleServiceAction = async () => {
        try {
            const response = await apiKeyApi.post('/job/123/event/start', {
                timestamp: Date.now()
            });
            console.log('Job started:', response.data);
        } catch (error) {
            console.error('Failed to start job:', error);
        }
    };

    return (
        <div>
        <button onClick= { handleUserAction } > Get Profile </button>
            < button onClick = { handleServiceAction } > Start Job </button>
                </div>
  );
};

// ============================================================================
// Custom API Client Instance
// ============================================================================

// Example 8: Creating a custom API client for specific use cases
import { ApiClient } from './api';

export const createCustomApiClient = () => {
    return new ApiClient({
        baseURL: '/api/v2', // Different API version
        timeout: 30000, // 30 second timeout
        headers: {
            'X-Custom-Header': 'custom-value',
            'Accept-Language': 'en-US'
        }
    });
};

export const exampleCustomClient = async () => {
    const customApi = createCustomApiClient();

    // Set authentication
    customApi.setAuthToken('your-token');

    try {
        const response = await customApi.get('/custom-endpoint');
        console.log('Custom response:', response.data);
    } catch (error) {
        console.error('Custom API error:', error);
    }
};

// ============================================================================
// TypeScript Type Examples
// ============================================================================

// Example 9: Using TypeScript types for better type safety
interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
}

interface Match {
    id: number;
    map: string;
    players: number;
    status: string;
    created_at: string;
}

interface ApiResponse<T> {
    data: T;
    message?: string;
    status: string;
}

export const exampleWithTypes = async () => {
    try {
        // Get user with proper typing
        const userResponse = await api.get<ApiResponse<User>>('/auth/user', { requireAuth: true });
        const user: User = userResponse.data.data;
        console.log('User name:', user.name);

        // Get matches with proper typing
        const matchesResponse = await api.get<ApiResponse<Match[]>>('/matches', {
            requireAuth: true,
            params: { page: 1, per_page: 10 }
        });
        const matches: Match[] = matchesResponse.data.data;
        console.log('First match map:', matches[0]?.map);

    } catch (error) {
        console.error('Typed API error:', error);
    }
};

// Example 10: Using the hook with types
export const TypedHookExample = () => {
    const { get, post } = useAuthenticatedApi();

    const handleTypedRequest = async () => {
        try {
            const response = await get<ApiResponse<User>>('/auth/user');
            const user = response.data.data;
            console.log('Typed user:', user.name);
        } catch (error) {
            console.error('Typed request failed:', error);
        }
    };

    return <button onClick={ handleTypedRequest }> Get Typed User </button>;
};
