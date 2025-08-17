import React from 'react';
import MapVisualization from '../components/map-visualization';

const GrenadeLibrary = () => {
  // Test grenade positions
  const testGrenadePositions = [
    { x: -192, y: 1696, type: 'red' },
    { x: -520, y: -2224, type: 'green' }
  ];

  return (
    <div className="p-6">
      <h1 className="text-2xl font-bold mb-6">Grenade Library</h1>

      <div className="mb-6">
        <h2 className="text-lg font-semibold mb-4">de_ancient - Test Position</h2>
        <p className="text-sm text-gray-600 mb-4">
          Testing coordinate conversion: (-192, 1696) in-game units
        </p>
        <MapVisualization
          mapName="de_ancient"
          grenadePositions={testGrenadePositions}
        />
      </div>
    </div>
  );
};

export default GrenadeLibrary;
