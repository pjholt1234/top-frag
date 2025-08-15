import { MatchesTable } from '@/components/matches-table';
import { api } from '../lib/api';
import { useState, useEffect } from 'react';

const YourMatches = () => {
  const [matches, setMatches] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchMatches = async () => {
      try {
        setLoading(true);
        const response = await api.get('/matches', { requireAuth: true });
        console.log(response.data);
        setMatches(response.data);
        setError(null);
      } catch (err: any) {
        console.error('Error fetching matches:', err);
        setError(err.message || 'Failed to fetch matches');
      } finally {
        setLoading(false);
      }
    };

    fetchMatches();
  }, []);

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
          <p className="text-muted-foreground">Loading matches...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <p className="text-destructive mb-2">Error loading matches</p>
          <p className="text-muted-foreground text-sm">{error}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">Your Matches</h1>
        <p className="text-muted-foreground">
          View your match history and detailed player statistics
        </p>
      </div>

      {matches.length === 0 ? (
        <div className="flex items-center justify-center min-h-[400px]">
          <div className="text-center">
            <p className="text-muted-foreground mb-2">No matches found</p>
            <p className="text-sm text-muted-foreground">
              Upload a demo file to see your matches here
            </p>
          </div>
        </div>
      ) : (
        <MatchesTable matches={matches} />
      )}
    </div>
  );
};

export default YourMatches;
