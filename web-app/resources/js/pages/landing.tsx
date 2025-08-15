import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { Card, CardDescription, CardHeader, CardTitle } from '../components/ui/card';
import { Loader2 } from 'lucide-react';

const LandingPage: React.FC = () => {
    const { user, loading } = useAuth();
    const navigate = useNavigate();

    useEffect(() => {
        if (!loading) {
            if (user) {
                navigate('/', { replace: true });
            } else {
                navigate('/login', { replace: true });
            }
        }
    }, [user, loading, navigate]);

    if (loading) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900">
                <Card className="w-full max-w-md">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Loader2 className="h-5 w-5 animate-spin" />
                            Loading...
                        </CardTitle>
                        <CardDescription>
                            Checking authentication status
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        );
    }

    return null;
};

export default LandingPage;
