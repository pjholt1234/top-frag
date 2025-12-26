import React, { useState, useEffect } from 'react';
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
import {
  Eye,
  EyeOff,
  Mail,
  Lock,
  User,
  AlertCircle,
  Code,
  Trash2,
  ToggleLeft,
  ToggleRight,
} from 'lucide-react';
import { IconBrandSteam, IconBrandDiscord } from '@tabler/icons-react';
import { useSteamSharecode } from '@/hooks/use-steam-sharecode';

const AccountSettingsPage: React.FC = () => {
  const {
    user,
    loading,
    changePassword,
    changeUsername,
    changeEmail,
    linkSteamAccount,
    unlinkSteamAccount,
    linkDiscordAccount,
    unlinkDiscordAccount,
  } = useAuth();

  const {
    hasSharecode,
    hasCompleteSetup,
    sharecodeAddedAt,
    loading: sharecodeLoading,
    saveSharecode,
    removeSharecode,
    toggleProcessing,
    checkSharecodeStatus,
  } = useSteamSharecode();
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

  // Discord linking state
  const [discordError, setDiscordError] = useState('');
  const [discordSuccess, setDiscordSuccess] = useState('');
  const [discordLoading, setDiscordLoading] = useState(false);

  // Steam sharecode state
  const [sharecodeData, setSharecodeData] = useState({
    steam_sharecode: user?.steam_sharecode || '',
    steam_game_auth_code: user?.steam_game_auth_code || '',
  });
  const [sharecodeError, setSharecodeError] = useState('');
  const [sharecodeSuccess, setSharecodeSuccess] = useState('');
  const [processingEnabled, setProcessingEnabled] = useState(
    user?.steam_match_processing_enabled || false
  );

  // Sync form state with user data
  useEffect(() => {
    if (user) {
      setSharecodeData({
        steam_sharecode: user.steam_sharecode || '',
        steam_game_auth_code: user.steam_game_auth_code || '',
      });
      setProcessingEnabled(user.steam_match_processing_enabled || false);
      // Check sharecode status to ensure we have the latest data
      checkSharecodeStatus();
    }
  }, [user, checkSharecodeStatus]);

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

  const handleDiscordLink = async () => {
    setDiscordError('');
    setDiscordSuccess('');
    setDiscordLoading(true);

    try {
      await linkDiscordAccount();
      setDiscordSuccess('Redirecting to Discord for account linking...');
    } catch (err: any) {
      setDiscordError(
        err?.response?.data?.message ||
          err?.message ||
          'Failed to link Discord account'
      );
    } finally {
      setDiscordLoading(false);
    }
  };

  const handleDiscordUnlink = async () => {
    setDiscordError('');
    setDiscordSuccess('');
    setDiscordLoading(true);

    try {
      await unlinkDiscordAccount();
      setDiscordSuccess('Discord account unlinked successfully');
    } catch (err: any) {
      setDiscordError(
        err?.response?.data?.message ||
          err?.message ||
          'Failed to unlink Discord account'
      );
    } finally {
      setDiscordLoading(false);
    }
  };

  const handleSharecodeSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSharecodeError('');
    setSharecodeSuccess('');

    if (!sharecodeData.steam_sharecode.trim()) {
      setSharecodeError('Please enter a Steam sharecode');
      return;
    }

    if (!sharecodeData.steam_game_auth_code.trim()) {
      setSharecodeError('Please enter a Steam game authentication code');
      return;
    }

    const sharecodePattern =
      /^CSGO-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/;
    if (!sharecodePattern.test(sharecodeData.steam_sharecode.trim())) {
      setSharecodeError(
        'Invalid sharecode format. Expected: CSGO-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX'
      );
      return;
    }

    const authCodePattern = /^[A-Z0-9]{4}-[A-Z0-9]{5}-[A-Z0-9]{4}$/;
    if (!authCodePattern.test(sharecodeData.steam_game_auth_code.trim())) {
      setSharecodeError(
        'Invalid game authentication code format. Expected: AAAA-AAAAA-AAAA'
      );
      return;
    }

    try {
      await saveSharecode(
        sharecodeData.steam_sharecode.trim(),
        sharecodeData.steam_game_auth_code.trim()
      );
      setSharecodeSuccess(
        'Steam sharecode and game authentication code saved successfully'
      );
    } catch (err: any) {
      setSharecodeError(
        err?.response?.data?.message ||
          err?.message ||
          'Failed to save sharecode'
      );
    }
  };

  const handleSharecodeRemove = async () => {
    setSharecodeError('');
    setSharecodeSuccess('');

    try {
      await removeSharecode();
      setSharecodeData({ steam_sharecode: '' });
      setSharecodeSuccess('Steam sharecode removed successfully');
    } catch (err: any) {
      setSharecodeError(
        err?.response?.data?.message ||
          err?.message ||
          'Failed to remove sharecode'
      );
    }
  };

  const handleToggleProcessing = async () => {
    setSharecodeError('');
    setSharecodeSuccess('');

    try {
      const newState = await toggleProcessing();
      setProcessingEnabled(newState);
      setSharecodeSuccess(
        newState
          ? 'Steam match processing enabled'
          : 'Steam match processing disabled'
      );
    } catch (err: any) {
      setSharecodeError(
        err?.response?.data?.message ||
          err?.message ||
          'Failed to toggle processing'
      );
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
    {
      id: 'discord',
      title: 'Discord Account',
      description: 'Link or unlink your Discord account',
      icon: IconBrandDiscord,
    },
    {
      id: 'sharecode',
      title: 'Steam Sharecode',
      description: 'Manage your Steam sharecode for match import',
      icon: Code,
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

          {/* Discord Account Section */}
          {activeSection === 'discord' && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <IconBrandDiscord className="h-5 w-5" />
                  <span>Discord Account</span>
                </CardTitle>
                <CardDescription>
                  Link or unlink your Discord account for enhanced features
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {discordError && (
                    <Alert variant="destructive">
                      <AlertCircle className="h-4 w-4" />
                      <AlertDescription>{discordError}</AlertDescription>
                    </Alert>
                  )}
                  {discordSuccess && (
                    <Alert>
                      <AlertDescription>{discordSuccess}</AlertDescription>
                    </Alert>
                  )}

                  <div className="space-y-4">
                    <div className="flex items-center justify-between p-4 border rounded-lg">
                      <div className="flex items-center space-x-3">
                        <IconBrandDiscord className="h-8 w-8 text-indigo-600" />
                        <div>
                          <p className="font-medium">Discord Account</p>
                          <p className="text-sm text-gray-600 dark:text-gray-400">
                            {user?.discord_id
                              ? `Linked (ID: ${user.discord_id})`
                              : 'Not linked'}
                          </p>
                        </div>
                      </div>
                      <div className="flex items-center space-x-2">
                        {user?.discord_id ? (
                          <Button
                            variant="destructive"
                            onClick={handleDiscordUnlink}
                            disabled={discordLoading}
                          >
                            {discordLoading ? 'Unlinking...' : 'Unlink Account'}
                          </Button>
                        ) : (
                          <Button
                            onClick={handleDiscordLink}
                            disabled={discordLoading}
                            className="bg-indigo-600 hover:bg-indigo-700"
                          >
                            {discordLoading ? 'Linking...' : 'Link Account'}
                          </Button>
                        )}
                      </div>
                    </div>

                    <div className="text-sm text-gray-600 dark:text-gray-400">
                      <p>Linking your Discord account allows you to:</p>
                      <ul className="list-disc list-inside mt-2 space-y-1">
                        <li>
                          Connect with other players in Discord communities
                        </li>
                        <li>Receive notifications and updates via Discord</li>
                        <li>Sync your profile across platforms</li>
                      </ul>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}

          {/* Steam Sharecode Section */}
          {activeSection === 'sharecode' && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center space-x-2">
                  <Code className="h-5 w-5" />
                  <span>Steam Sharecode</span>
                </CardTitle>
                <CardDescription>
                  Manage your Steam sharecode for automatic match history import
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-6">
                  {sharecodeError && (
                    <Alert variant="destructive">
                      <AlertCircle className="h-4 w-4" />
                      <AlertDescription>{sharecodeError}</AlertDescription>
                    </Alert>
                  )}
                  {sharecodeSuccess && (
                    <Alert>
                      <AlertDescription>{sharecodeSuccess}</AlertDescription>
                    </Alert>
                  )}

                  {/* Current Sharecode Status */}
                  <div className="space-y-4">
                    <div className="flex items-center justify-between p-4 border rounded-lg">
                      <div className="flex items-center space-x-3">
                        <Code className="h-8 w-8 text-blue-600" />
                        <div>
                          <p className="font-medium">Steam Configuration</p>
                          <p className="text-sm text-gray-600 dark:text-gray-400">
                            {hasCompleteSetup
                              ? `Complete setup (added ${sharecodeAddedAt ? new Date(sharecodeAddedAt).toLocaleDateString() : 'recently'})`
                              : hasSharecode
                                ? 'Partial setup - missing game authentication code'
                                : 'Not configured'}
                          </p>
                        </div>
                      </div>
                      {hasCompleteSetup && (
                        <Button
                          variant="destructive"
                          size="sm"
                          onClick={handleSharecodeRemove}
                          disabled={sharecodeLoading}
                        >
                          <Trash2 className="h-4 w-4 mr-2" />
                          {sharecodeLoading ? 'Removing...' : 'Remove'}
                        </Button>
                      )}
                    </div>

                    {/* Match Processing Toggle */}
                    {hasCompleteSetup && (
                      <div className="flex items-center justify-between p-4 border rounded-lg">
                        <div>
                          <p className="font-medium">
                            Automatic Match Processing
                          </p>
                          <p className="text-sm text-gray-600 dark:text-gray-400">
                            Automatically import matches from your Steam history
                          </p>
                        </div>
                        <Button
                          variant="outline"
                          onClick={handleToggleProcessing}
                          disabled={sharecodeLoading}
                          className="flex items-center gap-2"
                        >
                          {processingEnabled ? (
                            <ToggleRight className="h-4 w-4 text-green-600" />
                          ) : (
                            <ToggleLeft className="h-4 w-4 text-gray-400" />
                          )}
                          {processingEnabled ? 'Enabled' : 'Disabled'}
                        </Button>
                      </div>
                    )}
                  </div>

                  {/* Sharecode Input Form */}
                  <form onSubmit={handleSharecodeSubmit} className="space-y-4">
                    <div className="space-y-2">
                      <Label htmlFor="steam_sharecode">Steam Sharecode</Label>
                      <Input
                        id="steam_sharecode"
                        type="text"
                        placeholder="CSGO-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX"
                        value={sharecodeData.steam_sharecode}
                        onChange={e => {
                          setSharecodeData({
                            ...sharecodeData,
                            steam_sharecode: e.target.value.toUpperCase(),
                          });
                        }}
                        className="font-mono"
                        disabled={sharecodeLoading}
                      />
                      <p className="text-xs text-gray-600 dark:text-gray-400">
                        Find your sharecode in CS:GO match history → Share
                        button
                      </p>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="steam_game_auth_code">
                        Steam Game Authentication Code
                      </Label>
                      <Input
                        id="steam_game_auth_code"
                        type="text"
                        placeholder="AAAA-AAAAA-AAAA"
                        value={sharecodeData.steam_game_auth_code}
                        onChange={e => {
                          setSharecodeData({
                            ...sharecodeData,
                            steam_game_auth_code: e.target.value.toUpperCase(),
                          });
                        }}
                        className="font-mono"
                        disabled={sharecodeLoading}
                      />
                      <p className="text-xs text-gray-600 dark:text-gray-400">
                        Generate this code from{' '}
                        <a
                          href="https://help.steampowered.com/en/wizard/HelpWithGameIssue/?appid=730&issueid=128"
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-blue-600 hover:underline"
                        >
                          Steam help wizard
                        </a>
                      </p>
                    </div>

                    <Button
                      type="submit"
                      disabled={
                        sharecodeLoading ||
                        !sharecodeData.steam_sharecode.trim() ||
                        !sharecodeData.steam_game_auth_code.trim()
                      }
                      className="w-full"
                    >
                      {sharecodeLoading
                        ? 'Saving...'
                        : hasCompleteSetup
                          ? 'Update Configuration'
                          : 'Save Configuration'}
                    </Button>
                  </form>

                  {/* Help Text */}
                  <div className="bg-muted p-4 rounded-lg space-y-4">
                    <div>
                      <h4 className="text-sm font-medium mb-2">
                        How to find your sharecode:
                      </h4>
                      <ol className="text-sm text-muted-foreground space-y-1 list-decimal list-inside">
                        <li>Open CS:GO and go to your match history</li>
                        <li>Click on any recent match</li>
                        <li>Click the &quot;Share&quot; button</li>
                        <li>Copy the sharecode (starts with CSGO-)</li>
                      </ol>
                    </div>

                    <div>
                      <h4 className="text-sm font-medium mb-2">
                        How to get your game authentication code:
                      </h4>
                      <ol className="text-sm text-muted-foreground space-y-1 list-decimal list-inside">
                        <li>
                          Visit the{' '}
                          <a
                            href="https://help.steampowered.com/en/wizard/HelpWithGameIssue/?appid=730&issueid=128"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-blue-600 hover:underline"
                          >
                            Steam help wizard
                          </a>
                        </li>
                        <li>Sign in with your Steam account</li>
                        <li>
                          Follow the wizard to generate your authentication code
                        </li>
                        <li>
                          Copy the generated code (format: AAAA-AAAAA-AAAA)
                        </li>
                      </ol>
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
