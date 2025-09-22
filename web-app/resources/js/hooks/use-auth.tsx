import {
  useState,
  useEffect,
  createContext,
  useContext,
  ReactNode,
  useCallback,
} from 'react';
import { api } from '@/lib/api';

interface User {
  id: number;
  name: string;
  email: string | null;
  steam_id?: string;
  steam_avatar?: string;
  steam_avatar_medium?: string;
  steam_avatar_full?: string;
  steam_persona_name?: string;
}

interface AuthContextType {
  user: User | null;
  token: string | null;
  login: (email: string, password: string) => Promise<void>;
  register: (
    name: string,
    email: string,
    password: string,
    password_confirmation: string
  ) => Promise<void>;
  logout: () => Promise<void>;
  loginWithSteam: () => void;
  linkSteamAccount: () => Promise<void>;
  unlinkSteamAccount: () => Promise<void>;
  changePassword: (
    currentPassword: string,
    newPassword: string,
    confirmPassword: string
  ) => Promise<any>;
  changeUsername: (newUsername: string) => Promise<any>;
  changeEmail: (newEmail: string) => Promise<any>;
  loading: boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

interface AuthProviderProps {
  children: ReactNode;
}

export const AuthProvider = ({ children }: AuthProviderProps) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(
    localStorage.getItem('auth_token')
  );
  const [loading, setLoading] = useState(false);

  const fetchUser = useCallback(async () => {
    if (!token) return;

    try {
      api.setAuthToken(token);
      const response = await api.get('/auth/user', { requireAuth: true });
      setUser(response.data.user);
    } catch (error) {
      console.error('Error fetching user:', error);
      localStorage.removeItem('auth_token');
      setToken(null);
      setUser(null);
    }
  }, [token]);

  // Check if user is authenticated on mount
  useEffect(() => {
    if (token) {
      fetchUser();
    }
  }, [token, fetchUser]);

  const login = async (email: string, password: string) => {
    setLoading(true);
    try {
      const response = await api.post(
        '/auth/login',
        {
          email,
          password,
        },
        { requireAuth: false }
      );

      // Store token and user data
      localStorage.setItem('auth_token', response.data.token);
      setToken(response.data.token);
      setUser(response.data.user);
    } catch (error) {
      console.error('Login error:', error);
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const register = async (
    name: string,
    email: string,
    password: string,
    password_confirmation: string
  ) => {
    setLoading(true);
    try {
      const response = await api.post(
        '/auth/register',
        {
          name,
          email,
          password,
          password_confirmation,
        },
        { requireAuth: false }
      );

      // Store token and user data
      localStorage.setItem('auth_token', response.data.token);
      setToken(response.data.token);
      setUser(response.data.user);
    } catch (error) {
      console.error('Registration error:', error);
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const logout = async () => {
    setLoading(true);
    try {
      if (token) {
        await api.post('/auth/logout', {}, { requireAuth: true });
      }
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      // Clear local state regardless of API call success
      localStorage.removeItem('auth_token');
      setToken(null);
      setUser(null);
      setLoading(false);
    }
  };

  const loginWithSteam = () => {
    // Redirect to Steam authentication
    window.location.href = '/api/auth/steam/redirect';
  };

  const linkSteamAccount = async () => {
    setLoading(true);
    try {
      const response = await api.post(
        '/auth/steam/link',
        {},
        { requireAuth: true }
      );

      if (response.data.steam_redirect_url) {
        // Redirect to Steam for account linking
        window.location.href = response.data.steam_redirect_url;
      }
    } catch (error) {
      console.error('Steam linking error:', error);
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const unlinkSteamAccount = async () => {
    setLoading(true);
    try {
      await api.post('/auth/steam/unlink', {}, { requireAuth: true });
      // Refresh user data to reflect the change
      await fetchUser();
    } catch (error) {
      console.error('Steam unlinking error:', error);
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const changePassword = async (
    currentPassword: string,
    newPassword: string,
    confirmPassword: string
  ) => {
    setLoading(true);
    try {
      const response = await api.post(
        '/auth/change-password',
        {
          current_password: currentPassword,
          new_password: newPassword,
          new_password_confirmation: confirmPassword,
        },
        { requireAuth: true }
      );
      return response.data;
    } catch (error) {
      console.error('Password change error:', error);
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const changeUsername = async (newUsername: string) => {
    setLoading(true);
    try {
      const response = await api.post(
        '/auth/change-username',
        {
          new_username: newUsername,
        },
        { requireAuth: true }
      );
      // Update user data with new username
      if (response.data.user) {
        setUser(response.data.user);
      }
      return response.data;
    } catch (error) {
      console.error('Username change error:', error);
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const changeEmail = async (newEmail: string) => {
    setLoading(true);
    try {
      const response = await api.post(
        '/auth/change-email',
        {
          new_email: newEmail,
        },
        { requireAuth: true }
      );
      // Update user data with new email
      if (response.data.user) {
        setUser(response.data.user);
      }
      return response.data;
    } catch (error) {
      console.error('Email change error:', error);
      throw error;
    } finally {
      setLoading(false);
    }
  };

  const value: AuthContextType = {
    user,
    token,
    login,
    register,
    logout,
    loginWithSteam,
    linkSteamAccount,
    unlinkSteamAccount,
    changePassword,
    changeUsername,
    changeEmail,
    loading,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};
