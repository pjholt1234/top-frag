import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';

export const SteamCallbackPage: React.FC = () => {
  const [status, setStatus] = useState<'loading' | 'success' | 'error'>(
    'loading'
  );
  const [message, setMessage] = useState('');
  const navigate = useNavigate();

  useEffect(() => {
    const handleSteamCallback = () => {
      try {
        // Get the current URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        const success = urlParams.get('success');
        const error = urlParams.get('error');
        const message = urlParams.get('message');

        // Debug logging
        console.log('Steam callback URL params:', {
          token: token ? 'present' : 'missing',
          success,
          error,
          message: message ? decodeURIComponent(message) : 'none',
          fullUrl: window.location.href,
        });

        if (error) {
          setStatus('error');
          setMessage(
            decodeURIComponent(message || 'Steam authentication failed')
          );

          // Redirect to login after error
          setTimeout(() => {
            navigate('/login');
          }, 3000);
          return;
        }

        if (success) {
          if (token) {
            // New user or login - store the token
            localStorage.setItem('auth_token', token);
            setMessage(
              decodeURIComponent(message || 'Steam authentication successful!')
            );

            // Trigger a page reload to reinitialize auth context
            setTimeout(() => {
              window.location.href = '/onboarding';
            }, 2000);
          } else {
            // Account linking - no token needed
            setMessage(
              decodeURIComponent(
                message || 'Steam account linked successfully!'
              )
            );

            // Redirect to root page after a short delay
            setTimeout(() => {
              navigate('/');
            }, 2000);
          }

          setStatus('success');
        } else {
          throw new Error('No success status received from server');
        }
      } catch (error) {
        console.error('Steam callback error:', error);
        setStatus('error');
        setMessage(
          error instanceof Error ? error.message : 'Steam authentication failed'
        );

        // Redirect to login after error
        setTimeout(() => {
          navigate('/login');
        }, 3000);
      }
    };

    handleSteamCallback();
  }, [navigate]);

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="max-w-md w-full bg-white rounded-lg shadow-md p-6">
        <div className="text-center">
          {status === 'loading' && (
            <>
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
              <h2 className="text-xl font-semibold text-gray-900 mb-2">
                Authenticating with Steam...
              </h2>
              <p className="text-gray-600">
                Please wait while we complete your Steam authentication.
              </p>
            </>
          )}

          {status === 'success' && (
            <>
              <div className="text-green-500 text-4xl mb-4">✓</div>
              <h2 className="text-xl font-semibold text-gray-900 mb-2">
                Success!
              </h2>
              <p className="text-gray-600 mb-4">{message}</p>
              <p className="text-sm text-gray-500">Redirecting to setup...</p>
            </>
          )}

          {status === 'error' && (
            <>
              <div className="text-red-500 text-4xl mb-4">✗</div>
              <h2 className="text-xl font-semibold text-gray-900 mb-2">
                Authentication Failed
              </h2>
              <p className="text-gray-600 mb-4">{message}</p>
              <p className="text-sm text-gray-500">
                Redirecting to login page...
              </p>
            </>
          )}
        </div>
      </div>
    </div>
  );
};
