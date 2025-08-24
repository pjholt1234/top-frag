import React from 'react';
import { Skeleton } from '@/components/ui/skeleton';

const GrenadeListSkeleton: React.FC = () => {
  return (
    <div className="w-100 h-[575px] flex flex-col">
      <div className="p-4 pt-0 border-b flex items-center justify-between">
        <h3 className="font-semibold">Grenade List</h3>
        <div className="text-sm text-muted-foreground">
          <Skeleton className="h-4 w-20" />
        </div>
      </div>

      <div className="flex-1 overflow-y-auto p-2 space-y-2 custom-scrollbar">
        {/* Generate 8 skeleton cards */}
        {Array.from({ length: 8 }).map((_, index) => (
          <div key={index} className="border rounded-lg p-3">
            <div className="space-y-2">
              {/* Player name and round */}
              <div className="flex items-center justify-between">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-4 w-16" />
              </div>

              {/* Throw type and copy button */}
              <div className="flex items-center justify-between">
                <Skeleton className="h-3 w-20" />
                <Skeleton className="h-6 w-6 rounded" />
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default GrenadeListSkeleton;
