import React, { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Info, SkipForward } from 'lucide-react';

interface SteamSharecodeInputProps {
  onSuccess: (sharecode: string) => void;
  onSkip: () => void;
  loading?: boolean;
}

export function SteamSharecodeInput({
  onSuccess,
  onSkip,
  loading = false,
}: SteamSharecodeInputProps) {
  const [sharecode, setSharecode] = useState('');
  const [error, setError] = useState('');
  const [isValidating, setIsValidating] = useState(false);

  const validateSharecode = (code: string): boolean => {
    const pattern =
      /^CSGO-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}-[A-Z0-9]{5}$/;
    return pattern.test(code);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (!sharecode.trim()) {
      setError('Please enter a Steam sharecode');
      return;
    }

    if (!validateSharecode(sharecode.trim())) {
      setError(
        'Invalid sharecode format. Expected: CSGO-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX'
      );
      return;
    }

    setIsValidating(true);
    try {
      onSuccess(sharecode.trim());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save sharecode');
    } finally {
      setIsValidating(false);
    }
  };

  const handleSkip = () => {
    setError('');
    onSkip();
  };

  return (
    <Card className="w-full max-w-2xl mx-auto">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Info className="h-5 w-5" />
          Steam Sharecode Setup
        </CardTitle>
        <CardDescription>
          Add your Steam sharecode to automatically import your match history
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        <div className="space-y-4">
          <div>
            <h3 className="text-sm font-medium mb-2">
              What is a Steam sharecode?
            </h3>
            <p className="text-sm text-muted-foreground mb-3">
              A sharecode is a unique identifier for your CS:GO matches that
              allows us to automatically import your match history. You can find
              it in your CS:GO match history.
            </p>
          </div>

          <div className="bg-muted p-4 rounded-lg">
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

          <div className="flex items-start gap-2 p-3 bg-blue-50 dark:bg-blue-950/20 rounded-lg">
            <Info className="h-4 w-4 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
            <div className="text-sm">
              <p className="text-blue-800 dark:text-blue-200 font-medium">
                Tip:
              </p>
              <p className="text-blue-700 dark:text-blue-300">
                Use a recent sharecode (not necessarily the oldest). You can
                always update it later in your settings.
              </p>
            </div>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="sharecode">Steam Sharecode</Label>
            <Input
              id="sharecode"
              type="text"
              placeholder="CSGO-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX"
              value={sharecode}
              onChange={e => {
                setSharecode(e.target.value.toUpperCase());
                setError('');
              }}
              disabled={loading || isValidating}
              className="font-mono"
            />
            {error && (
              <Alert variant="destructive">
                <AlertDescription>{error}</AlertDescription>
              </Alert>
            )}
          </div>

          <div className="flex gap-3">
            <Button
              type="submit"
              disabled={loading || isValidating || !sharecode.trim()}
              className="flex-1"
            >
              {isValidating ? 'Saving...' : 'Save Sharecode'}
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={handleSkip}
              disabled={loading || isValidating}
              className="flex items-center gap-2"
            >
              <SkipForward className="h-4 w-4" />
              Skip for Now
            </Button>
          </div>
        </form>

        <div className="text-center">
          <p className="text-xs text-muted-foreground">
            You can add or update your sharecode anytime in your account
            settings
          </p>
        </div>
      </CardContent>
    </Card>
  );
}
