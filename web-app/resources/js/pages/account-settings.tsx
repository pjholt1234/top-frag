import React, { useState } from 'react';
import { useAuth } from '@/hooks/use-auth';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Eye, EyeOff, Mail, Lock, User, AlertCircle } from 'lucide-react';
import { IconBrandSteam } from '@tabler/icons-react';

const AccountSettingsPage: React.FC = () => {
  const {
    user,
    loading,
    changePassword,
    changeUsername,
    changeEmail,
    linkSteamAccount,
    unlinkSteamAccount,
  } = useAuth();
  const [activeSection, setActiveSection] = useState<string>('password');

  // Password reset state
  const [passwordData, setPasswordData] = useState({
    currentPassword: '',
    newPassword: '',
    confirmPassword: '',
  });
  const [showPasswords, setShowPasswords] = useState({
    current: false,
    new: false,
    confirm: false,
  });
  const [passwordError, setPasswordError] = useState('');
  const [passwordSuccess, setPasswordSuccess] = useState('');
  const [passwordLoading, setPasswordLoading] = useState(false);

  // Username change state
  const [usernameData, setUsernameData] = useState({
    newUsername: user?.name || '',
  });
  const [usernameError, setUsernameError] = useState('');
  const [usernameSuccess, setUsernameSuccess] = useState('');
  const [usernameLoading, setUsernameLoading] = useState(false);

  // Email change state
  const [emailData, setEmailData] = useState({
    newEmail: user?.email || '',
  });
  const [emailError, setEmailError] = useState('');
  const [emailSuccess, setEmailSuccess] = useState('');
  const [emailLoading, setEmailLoading] = useState(false);

  // Steam linking state
  const [steamError, setSteamError] = useState('');
  const [steamSuccess, setSteamSuccess] = useState('');
  const [steamLoading, setSteamLoading] = useState(false);

  const handlePasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setPasswordError('');
    setPasswordSuccess('');
    setPasswordLoading(true);

    if (passwordData.newPassword !== passwordData.confirmPassword) {
      setPasswordError('New passwords do not match');
      setPasswordLoading(false);
      return;
    }

    if (passwordData.newPassword.length < 8) {
      setPasswordError('New password must be at least 8 characters long');
      setPasswordLoading(false);
      return;
    }

    try {
      await changePassword(
        passwordData.currentPassword,
        passwordData.newPassword,
        passwordData.confirmPassword
      );
      setPasswordSuccess('Password changed successfully');
      setPasswordData({
        currentPassword: '',
        newPassword: '',
        confirmPassword: '',
      });
    } catch (err: any) {
      setPasswordError(
        err?.response?.data?.message ||
          err?.message ||
          'Failed to change password'
      );
    } finally {
      setPasswordLoading(false);
    }
  };

  const handleUsernameSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setUsernameError('');
    setUsernameSuccess('');
    setUsernameLoading(true);

    if (!usernameData.newUsername.trim()) {
      setUsernameError('Username cannot be empty');
      setUsernameLoading(false);
      return;
    }

    if (usernameData.newUsername === user?.name) {
      setUsernameError('New username must be different from current username');
      setUsernameLoading(false);
      return;
    }

    try {
      await changeUsername(usernameData.newUsername);
      setUsernameSuccess('Username changed successfully');
    } catch (err: any) {
      setUsernameError(
        err?.response?.data?.message ||
          err?.message ||
          'Failed to change username'
      );
    } finally {
      setUsernameLoading(false);
    }
  };

  const handleEmailSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setEmailError('');
    setEmailSuccess('');
    setEmailLoading(true);

    if (!emailData.newEmail.trim()) {
      setEmailError('Email cannot be empty');
      setEmailLoading(false);
      return;
    }

    if (emailData.newEmail === user?.email) {
      setEmailError('New email must be different from current email');
      setEmailLoading(false);
      return;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(emailData.newEmail)) {
      setEmailError('Please enter a valid email address');
      setEmailLoading(false);
      return;
    }

    try {
      await changeEmail(emailData.newEmail);
      setEmailSuccess('Email changed successfully');
    } catch (err: any) {
      setEmailError(
        err?.response?.data?.message || err?.message || 'Failed to change email'
      );
    } finally {
      setEmailLoading(false);
    }
  };

  const handleSteamLink = async () => {
    setSteamError('');
    setSteamSuccess('');
    setSteamLoading(true);

    try {
      await linkSteamAccount();
      setSteamSuccess('Redirecting to Steam for account linking...');
    } catch (err: any) {
      setSteamError(
        err?.response?.data?.message ||
          err?.message ||
          'Failed to link Steam account'
      );
    } finally {
      setSteamLoading(false);
    }
  };

  const handleSteamUnlink = async () => {
    setSteamError('');
    setSteamSuccess('');
    setSteamLoading(true);

    try {
      await unlinkSteamAccount();
      setSteamSuccess('Steam account unlinked successfully');
    } catch (err: any) {
      setSteamError(
        err?.response?.data?.message ||
          err?.message ||
          'Failed to unlink Steam account'
      );
    } finally {
      setSteamLoading(false);
    }
  };

  const settingsSections = [
    {
      id: 'password',
      title: 'Password',
      description: 'Change your account password',
      icon: Lock,
    },
    {
      id: 'username',
      title: 'Username',
      description: 'Change your display name',
      icon: User,
    },
    {
      id: 'email',
      title: 'Email',
      description: 'Change your email address',
      icon: Mail,
    },
    {
      id: 'steam',
      title: 'Steam Account',
      description: 'Link or unlink your Steam account',
      icon: IconBrandSteam,
    },
  ];

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-custom-orange mx-auto"></div>
          <p className="mt-2 text-gray-600 dark:text-gray-400">Loading...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8 max-w-4xl">
      <div className="mb-8">
        <h1 className="text-3xl font-bold text-gray-900 dark:text-white">
          Account Settings
        </h1>
        <p className="text-gray-600 dark:text-gray-400 mt-2">
          Manage your account preferences and security settings
        </p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Settings Navigation */}
        <div className="lg:col-span-1">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Settings</CardTitle>
            </CardHeader>
            <CardContent className="p-0">
              <nav className="space-y-1">
                {settingsSections.map(section => {
                  const Icon = section.icon;
                  return (
                    <button
                      key={section.id}
                      onClick={() => setActiveSection(section.id)}
                      className={`w-full text-left px-4 py-3 text-sm font-medium transition-colors ${
                        activeSection === section.id
                          ? 'bg-custom-orange text-white'
                          : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800'
                      }`}
                    >
                      <div className="flex items-center space-x-3">
                        <Icon className="h-4 w-4" />
                        <div>
                          <div>{section.title}</div>
                          <div className="text-xs opacity-75">
                            {section.description}
                          </div>
                        </div>
                      </div>
                    </button>
                  );
                })}
              </nav>
            </CardContent>
          </Card>
        </div>

        {/* Settings Content */}
        <div className="lg:col-span-3">
          {/* Password Reset Section */}
          {activeSection === 'password' && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Lock className="h-5 w-5" />
                  <span>Change Password</span>
                </CardTitle>
                <CardDescription>
                  Update your password to keep your account secure
                </CardDescription>
              </CardHeader>
              <CardContent>
                <form onSubmit={handlePasswordSubmit} className="space-y-4">
                  {passwordError && (
                    <Alert variant="destructive">
                      <AlertCircle className="h-4 w-4" />
                      <AlertDescription>{passwordError}</AlertDescription>
                    </Alert>
                  )}
                  {passwordSuccess && (
                    <Alert>
                      <AlertDescription>{passwordSuccess}</AlertDescription>
                    </Alert>
                  )}

                  <div className="space-y-2">
                    <Label htmlFor="currentPassword">Current Password</Label>
                    <div className="relative">
                      <Input
                        id="currentPassword"
                        type={showPasswords.current ? 'text' : 'password'}
                        value={passwordData.currentPassword}
                        onChange={e =>
                          setPasswordData({
                            ...passwordData,
                            currentPassword: e.target.value,
                          })
                        }
                        required
                      />
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                        onClick={() =>
                          setShowPasswords({
                            ...showPasswords,
                            current: !showPasswords.current,
                          })
                        }
                      >
                        {showPasswords.current ? (
                          <EyeOff className="h-4 w-4" />
                        ) : (
                          <Eye className="h-4 w-4" />
                        )}
                      </Button>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="newPassword">New Password</Label>
                    <div className="relative">
                      <Input
                        id="newPassword"
                        type={showPasswords.new ? 'text' : 'password'}
                        value={passwordData.newPassword}
                        onChange={e =>
                          setPasswordData({
                            ...passwordData,
                            newPassword: e.target.value,
                          })
                        }
                        required
                        minLength={8}
                      />
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                        onClick={() =>
                          setShowPasswords({
                            ...showPasswords,
                            new: !showPasswords.new,
                          })
                        }
                      >
                        {showPasswords.new ? (
                          <EyeOff className="h-4 w-4" />
                        ) : (
                          <Eye className="h-4 w-4" />
                        )}
                      </Button>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="confirmPassword">
                      Confirm New Password
                    </Label>
                    <div className="relative">
                      <Input
                        id="confirmPassword"
                        type={showPasswords.confirm ? 'text' : 'password'}
                        value={passwordData.confirmPassword}
                        onChange={e =>
                          setPasswordData({
                            ...passwordData,
                            confirmPassword: e.target.value,
                          })
                        }
                        required
                        minLength={8}
                      />
                      <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="absolute right-0 top-0 h-full px-3 py-2 hover:bg-transparent"
                        onClick={() =>
                          setShowPasswords({
                            ...showPasswords,
                            confirm: !showPasswords.confirm,
                          })
                        }
                      >
                        {showPasswords.confirm ? (
                          <EyeOff className="h-4 w-4" />
                        ) : (
                          <Eye className="h-4 w-4" />
                        )}
                      </Button>
                    </div>
                  </div>

                  <Button
                    type="submit"
                    disabled={passwordLoading}
                    className="w-full"
                  >
                    {passwordLoading
                      ? 'Updating Password...'
                      : 'Update Password'}
                  </Button>
                </form>
              </CardContent>
            </Card>
          )}

          {/* Username Change Section */}
          {activeSection === 'username' && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <User className="h-5 w-5" />
                  <span>Change Username</span>
                </CardTitle>
                <CardDescription>
                  Update your display name that appears throughout the
                  application
                </CardDescription>
              </CardHeader>
              <CardContent>
                <form onSubmit={handleUsernameSubmit} className="space-y-4">
                  {usernameError && (
                    <Alert variant="destructive">
                      <AlertCircle className="h-4 w-4" />
                      <AlertDescription>{usernameError}</AlertDescription>
                    </Alert>
                  )}
                  {usernameSuccess && (
                    <Alert>
                      <AlertDescription>{usernameSuccess}</AlertDescription>
                    </Alert>
                  )}

                  <div className="space-y-2">
                    <Label htmlFor="currentUsername">Current Username</Label>
                    <Input
                      id="currentUsername"
                      value={user?.name || ''}
                      disabled
                      className="bg-gray-50 dark:bg-gray-800"
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="newUsername">New Username</Label>
                    <Input
                      id="newUsername"
                      value={usernameData.newUsername}
                      onChange={e =>
                        setUsernameData({
                          ...usernameData,
                          newUsername: e.target.value,
                        })
                      }
                      required
                      minLength={2}
                      maxLength={50}
                    />
                  </div>

                  <Button
                    type="submit"
                    disabled={usernameLoading}
                    className="w-full"
                  >
                    {usernameLoading
                      ? 'Updating Username...'
                      : 'Update Username'}
                  </Button>
                </form>
              </CardContent>
            </Card>
          )}

          {/* Email Change Section */}
          {activeSection === 'email' && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Mail className="h-5 w-5" />
                  <span>Change Email</span>
                </CardTitle>
                <CardDescription>
                  Update your email address for account notifications and login
                </CardDescription>
              </CardHeader>
              <CardContent>
                <form onSubmit={handleEmailSubmit} className="space-y-4">
                  {emailError && (
                    <Alert variant="destructive">
                      <AlertCircle className="h-4 w-4" />
                      <AlertDescription>{emailError}</AlertDescription>
                    </Alert>
                  )}
                  {emailSuccess && (
                    <Alert>
                      <AlertDescription>{emailSuccess}</AlertDescription>
                    </Alert>
                  )}

                  <div className="space-y-2">
                    <Label htmlFor="currentEmail">Current Email</Label>
                    <Input
                      id="currentEmail"
                      value={user?.email || ''}
                      disabled
                      className="bg-gray-50 dark:bg-gray-800"
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="newEmail">New Email</Label>
                    <Input
                      id="newEmail"
                      type="email"
                      value={emailData.newEmail}
                      onChange={e =>
                        setEmailData({ ...emailData, newEmail: e.target.value })
                      }
                      required
                    />
                  </div>

                  <Button
                    type="submit"
                    disabled={emailLoading}
                    className="w-full"
                  >
                    {emailLoading ? 'Updating Email...' : 'Update Email'}
                  </Button>
                </form>
              </CardContent>
            </Card>
          )}

          {/* Steam Account Section */}
          {activeSection === 'steam' && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <IconBrandSteam className="h-5 w-5" />
                  <span>Steam Account</span>
                </CardTitle>
                <CardDescription>
                  Link or unlink your Steam account for enhanced features
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {steamError && (
                    <Alert variant="destructive">
                      <AlertCircle className="h-4 w-4" />
                      <AlertDescription>{steamError}</AlertDescription>
                    </Alert>
                  )}
                  {steamSuccess && (
                    <Alert>
                      <AlertDescription>{steamSuccess}</AlertDescription>
                    </Alert>
                  )}

                  <div className="space-y-4">
                    <div className="flex items-center justify-between p-4 border rounded-lg">
                      <div className="flex items-center space-x-3">
                        <IconBrandSteam className="h-8 w-8 text-blue-600" />
                        <div>
                          <p className="font-medium">Steam Account</p>
                          <p className="text-sm text-gray-600 dark:text-gray-400">
                            {user?.steam_id
                              ? `Linked (ID: ${user.steam_id})`
                              : 'Not linked'}
                          </p>
                        </div>
                      </div>
                      <div className="flex items-center space-x-2">
                        {user?.steam_id ? (
                          <Button
                            variant="destructive"
                            onClick={handleSteamUnlink}
                            disabled={steamLoading}
                          >
                            {steamLoading ? 'Unlinking...' : 'Unlink Account'}
                          </Button>
                        ) : (
                          <Button
                            onClick={handleSteamLink}
                            disabled={steamLoading}
                            className="bg-blue-600 hover:bg-blue-700"
                          >
                            {steamLoading ? 'Linking...' : 'Link Account'}
                          </Button>
                        )}
                      </div>
                    </div>

                    <div className="text-sm text-gray-600 dark:text-gray-400">
                      <p>Linking your Steam account allows you to:</p>
                      <ul className="list-disc list-inside mt-2 space-y-1">
                        <li>Access match data from your Steam games</li>
                        <li>
                          View detailed statistics and performance metrics
                        </li>
                        <li>Sync your gaming profile across platforms</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
};

export default AccountSettingsPage;
