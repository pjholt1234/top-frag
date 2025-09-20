import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '@/hooks/use-auth';
import { SteamSharecodeInput } from '@/components/steam-sharecode-input';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { CheckCircle, ArrowRight } from 'lucide-react';

type OnboardingStep = 'sharecode' | 'complete';

export function OnboardingPage() {
  const [currentStep, setCurrentStep] = useState<OnboardingStep>('sharecode');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  const { user } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    if (!user) {
      navigate('/login');
      return;
    }

    // Check if user already has a sharecode configured
    if (user.steam_sharecode) {
      // User already has sharecode, skip onboarding
      navigate('/');
      return;
    }
  }, [user, navigate]);

  const handleSharecodeSuccess = async (sharecode: string) => {
    setLoading(true);
    setError('');

    try {
      const response = await fetch('/api/steam-sharecode', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
        },
        body: JSON.stringify({ steam_sharecode: sharecode }),
      });

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.message || 'Failed to save sharecode');
      }

      setSuccess('Sharecode saved successfully!');
      setCurrentStep('complete');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save sharecode');
    } finally {
      setLoading(false);
    }
  };

  const handleSkip = () => {
    setCurrentStep('complete');
  };

  const handleComplete = () => {
    navigate('/');
  };

  if (!user) {
    return null;
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 py-12 px-4 sm:px-6 lg:px-8">
      <div className="w-full max-w-2xl">
        {currentStep === 'sharecode' && (
          <div className="space-y-6">
            <div className="text-center">
              <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                Welcome to Top Frag, {user.name}!
              </h1>
              <p className="mt-2 text-gray-600 dark:text-gray-400">
                Let&apos;s set up your account to get the most out of your CS:GO
                analysis
              </p>
            </div>

            <SteamSharecodeInput
              onSuccess={handleSharecodeSuccess}
              onSkip={handleSkip}
              loading={loading}
            />

            {error && (
              <Alert variant="destructive">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}
          </div>
        )}

        {currentStep === 'complete' && (
          <Card className="w-full max-w-md mx-auto">
            <CardHeader className="text-center">
              <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/20">
                <CheckCircle className="h-6 w-6 text-green-600 dark:text-green-400" />
              </div>
              <CardTitle>Setup Complete!</CardTitle>
              <CardDescription>
                {success
                  ? 'Your Steam sharecode has been saved and you&apos;re all set!'
                  : 'You can always add your Steam sharecode later in your account settings.'}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Button onClick={handleComplete} className="w-full">
                Continue to Dashboard
                <ArrowRight className="ml-2 h-4 w-4" />
              </Button>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  );
}
