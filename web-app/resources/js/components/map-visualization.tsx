import React, { useEffect, useRef, useState, useCallback } from 'react';
import { getMapMetadata } from '../config/maps';

interface MapVisualizationProps {
    mapName: string;
    grenadePositions?: Array<{
        x: number;
        y: number;
        grenade_type?: string;
        player_name?: string;
        round_number?: number;
        player_x?: number;
        player_y?: number;
    }>;
}

const MapVisualization: React.FC<MapVisualizationProps> = ({
    mapName,
    grenadePositions = [],
}) => {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const imageRef = useRef<HTMLImageElement>(null);
    const [imageLoaded, setImageLoaded] = useState(false);
    const [selectedMarkerIndex, setSelectedMarkerIndex] = useState<number | null>(null);

    // Get map metadata for coordinate conversion
    const mapMetadata = getMapMetadata(mapName);

    // Convert in-game coordinates to pixel coordinates
    const convertGameToPixelCoords = useCallback(
        (gameX: number, gameY: number) => {
            if (!mapMetadata) {
                console.error(`Map metadata not found for: ${mapName}`);
                return { x: 0, y: 0 };
            }

            // Apply offset to get coordinates relative to radar origin
            const offsetX = gameX + mapMetadata.offset.x;
            const offsetY = gameY + mapMetadata.offset.y;

            // Convert to pixel coordinates
            const pixelX = offsetX / mapMetadata.resolution;
            const pixelY = 1024 - offsetY / mapMetadata.resolution; // Flip Y-axis

            return { x: pixelX, y: pixelY };
        },
        [mapMetadata, mapName]
    );

    // Convert pixel coordinates back to in-game coordinates (reverse calculation)
    // const convertPixelToGameCoords = (pixelX: number, pixelY: number) => {
    //     if (!mapMetadata) {
    //         console.error(`Map metadata not found for: ${mapName}`);
    //         return { x: 0, y: 0 };
    //     }

    //     // Convert pixel coordinates to in-game units
    //     const offsetX = pixelX * mapMetadata.resolution;
    //     const offsetY = pixelY * mapMetadata.resolution;

    //     // Remove offset
    //     const gameX = offsetX - mapMetadata.offset.x;
    //     const gameY = offsetY - mapMetadata.offset.y;

    //     return { x: gameX, y: gameY };
    // };

    // Draw grenade positions on canvas
    const drawGrenadePositions = useCallback(() => {
        const canvas = canvasRef.current;
        const ctx = canvas?.getContext('2d');

        if (!canvas || !ctx || !imageLoaded) return;

        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw grenade positions
        grenadePositions.forEach((position, index) => {
            const pixelCoords = convertGameToPixelCoords(position.x, position.y);

            // Get color based on grenade type
            const colors: { [key: string]: string } = {
                incendiary: '#ff0000', // Red
                molotov: '#ff0000', // Red
                smoke: '#ffffff', // White
                he: '#ffa500', // Orange
                flashbang: '#ffff00', // Yellow
                decoy: '#0000ff', // Blue
            };

            const fillColor = colors[position.grenade_type || ''] || '#ff0000';

            // Set opacity based on selection
            const isSelected = selectedMarkerIndex === index;
            const opacity = isSelected ? 1.0 : (selectedMarkerIndex !== null ? 0.3 : 1.0);

            ctx.globalAlpha = opacity;

            // Draw colored circle
            ctx.beginPath();
            ctx.arc(pixelCoords.x, pixelCoords.y, 8, 0, 2 * Math.PI);
            ctx.fillStyle = fillColor;
            ctx.fill();
            ctx.strokeStyle = 'white';
            ctx.lineWidth = 2;
            ctx.stroke();

            // If this marker is selected, draw line to player position
            if (isSelected && position.player_x !== undefined && position.player_y !== undefined) {
                const playerPixelCoords = convertGameToPixelCoords(position.player_x, position.player_y);

                // Draw dotted line
                ctx.setLineDash([5, 5]);
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.6)';
                ctx.lineWidth = 2;
                ctx.beginPath();
                ctx.moveTo(pixelCoords.x, pixelCoords.y);
                ctx.lineTo(playerPixelCoords.x, playerPixelCoords.y);
                ctx.stroke();

                // Draw X at player position
                ctx.setLineDash([]);
                ctx.strokeStyle = 'rgba(255, 255, 255, 0.8)';
                ctx.lineWidth = 3;
                ctx.beginPath();
                ctx.moveTo(playerPixelCoords.x - 6, playerPixelCoords.y - 6);
                ctx.lineTo(playerPixelCoords.x + 6, playerPixelCoords.y + 6);
                ctx.moveTo(playerPixelCoords.x - 6, playerPixelCoords.y + 6);
                ctx.lineTo(playerPixelCoords.x + 6, playerPixelCoords.y - 6);
                ctx.stroke();
            }

            // Reset global alpha
            ctx.globalAlpha = 1.0;
        });
    }, [grenadePositions, imageLoaded, convertGameToPixelCoords, selectedMarkerIndex]);

    // Handle image load
    const handleImageLoad = () => {
        const canvas = canvasRef.current;
        const image = imageRef.current;

        if (canvas && image) {
            // Set canvas internal resolution to match display size (1024x1024)
            canvas.width = 1024;
            canvas.height = 1024;
            setImageLoaded(true);
        }
    };

    // Redraw when grenade positions change or image loads
    useEffect(() => {
        if (imageLoaded) {
            drawGrenadePositions();
        }
    }, [drawGrenadePositions, imageLoaded]);

    // Handle click on canvas
    const handleCanvasClick = (event: React.MouseEvent<HTMLCanvasElement>) => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        const rect = canvas.getBoundingClientRect();
        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;

        // Scale coordinates to match canvas internal resolution
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        const canvasX = x * scaleX;
        const canvasY = y * scaleY;

        // Check if click is on a marker
        let clickedMarkerIndex = -1;
        grenadePositions.forEach((position, index) => {
            const pixelCoords = convertGameToPixelCoords(position.x, position.y);
            const distance = Math.sqrt(
                Math.pow(canvasX - pixelCoords.x, 2) + Math.pow(canvasY - pixelCoords.y, 2)
            );

            if (distance <= 12) { // Click radius slightly larger than marker radius
                clickedMarkerIndex = index;
            }
        });

        if (clickedMarkerIndex !== -1) {
            setSelectedMarkerIndex(clickedMarkerIndex);
        } else {
            setSelectedMarkerIndex(null);
        }
    };

    // Handle mouse move to show cursor coordinates
    const handleMouseMove = (_event: React.MouseEvent<HTMLDivElement>) => {
        // const rect = event.currentTarget.getBoundingClientRect();
        // const x = event.clientX - rect.left;
        // const y = event.clientY - rect.top;
        // Convert to in-game coordinates
        // const gameCoords = convertPixelToGameCoords(x, y);
        // TODO: Use gameCoords for tooltip or other functionality
    };

    return (
        <div
            className="relative inline-block"
            onMouseMove={handleMouseMove}
            style={{ cursor: 'crosshair' }}
        >
            <img
                ref={imageRef}
                src={mapMetadata?.imagePath || `/images/maps/${mapName}.png`}
                alt={`${mapName} map`}
                onLoad={handleImageLoad}
                className="block"
                style={{ width: '512px', height: '512px' }}
            />
            <canvas
                ref={canvasRef}
                className="absolute top-0 left-0 cursor-pointer"
                style={{ width: '512px', height: '512px' }}
                onClick={handleCanvasClick}
            />
        </div>
    );
};

export default MapVisualization;
