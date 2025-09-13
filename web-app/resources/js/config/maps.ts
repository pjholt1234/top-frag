export interface MapMetadata {
  name: string;
  displayName: string;
  imagePath: string;
  logoPath: string;
  backgroundPath: string;
  backgroundPosition: {
    x: number;
    y: number;
  };
  backgroundScale: number;
  resolution: number;
  offset: {
    x: number;
    y: number;
  };
  includesMultipleFloors?: boolean;
  floors?: Array<{
    heightBounds: {
      min: number;
      max: number;
    };
    offset: {
      x: number;
      y: number;
    };
  }>;
}

export const mapsConfig: Record<string, MapMetadata> = {
  de_ancient: {
    name: 'de_ancient',
    displayName: 'Ancient',
    imagePath: '/images/maps/de_ancient.png',
    logoPath: '/images/map-logos/ancient.png',
    backgroundPath: '/images/map-backgrounds/ancient.webp',
    backgroundPosition: {
      x: 50,
      y: 50,
    },
    backgroundScale: 1.2,
    resolution: 4.26,
    offset: {
      x: 2590,
      y: 2520,
    },
  },
  de_dust2: {
    name: 'de_dust2',
    displayName: 'Dust II',
    imagePath: '/images/maps/de_dust2.png',
    logoPath: '/images/map-logos/dust2.png',
    backgroundPath: '/images/map-backgrounds/dust2.webp',
    backgroundPosition: {
      x: 50,
      y: 50,
    },
    backgroundScale: 1.2,
    resolution: 4.4,
    offset: {
      x: 2470,
      y: 1255,
    },
  },
  de_mirage: {
    name: 'de_mirage',
    displayName: 'Mirage',
    imagePath: '/images/maps/de_mirage.png',
    logoPath: '/images/map-logos/mirage.png',
    backgroundPath: '/images/map-backgrounds/mirage.webp',
    backgroundPosition: {
      x: 50,
      y: 50,
    },
    backgroundScale: 1.2,
    resolution: 5.02,
    offset: {
      x: 3240,
      y: 3410,
    },
  },
  de_inferno: {
    name: 'de_inferno',
    displayName: 'Inferno',
    imagePath: '/images/maps/de_inferno.png',
    logoPath: '/images/map-logos/inferno.png',
    backgroundPath: '/images/map-backgrounds/inferno-background.webp',
    backgroundPosition: {
      x: 50,
      y: 50,
    },
    backgroundScale: 1.2,
    resolution: 4.91,
    offset: {
      x: 2090,
      y: 1150,
    },
  },
  de_nuke: {
    name: 'de_nuke',
    displayName: 'Nuke',
    imagePath: '/images/maps/de_nuke.png',
    logoPath: '/images/map-logos/nuke.png',
    backgroundPath: '/images/map-backgrounds/nuke-background.webp',
    backgroundPosition: {
      x: 50,
      y: 50,
    },
    backgroundScale: 1.2,
    resolution: 6.98,
    offset: {
      x: 3290,
      y: 5990,
    },
    includesMultipleFloors: true,
    floors: [
      {
        heightBounds: {
          min: -2500,
          max: -480,
        },
        offset: {
          x: 0,
          y: -46,
        },
      },
    ],
  },
  de_overpass: {
    name: 'de_overpass',
    displayName: 'Overpass',
    imagePath: '/images/maps/de_overpass.png',
    logoPath: '/images/map-logos/overpass.png',
    backgroundPath: '/images/map-backgrounds/overpass.webp',
    backgroundPosition: {
      x: 50,
      y: 50,
    },
    backgroundScale: 1.2,
    resolution: 5.18,
    offset: {
      x: 4830,
      y: 3540,
    },
  },
  de_train: {
    name: 'de_train',
    displayName: 'Train',
    imagePath: '/images/maps/de_train.png',
    logoPath: '/images/map-logos/train.png',
    backgroundPath: '/images/map-backgrounds/train-background.webp',
    backgroundPosition: {
      x: 50,
      y: 50,
    },
    backgroundScale: 1.2,
    resolution: 4.74,
    offset: {
      x: 2730,
      y: 2360,
    },
  },
  de_cache: {
    name: 'de_cache',
    displayName: 'Cache',
    imagePath: '/images/maps/de_cache.png',
    logoPath: '/images/map-logos/cache.png',
    backgroundPath: '/images/map-backgrounds/cache.webp',
    backgroundPosition: {
      x: 50,
      y: 50,
    },
    backgroundScale: 1.2,
    resolution: 5.54,
    offset: {
      x: 2020,
      y: 2390,
    },
  },
  de_anubis: {
    name: 'de_anubis',
    displayName: 'Anubis',
    imagePath: '/images/maps/de_anubis.png',
    logoPath: '/images/map-logos/anubis.png',
    backgroundPath: '/images/map-backgrounds/anubis.webp',
    backgroundPosition: {
      x: 50,
      y: 50,
    },
    backgroundScale: 1.2,
    resolution: 5.25,
    offset: {
      x: 2830,
      y: 2030,
    },
  },
  de_vertigo: {
    name: 'de_vertigo',
    displayName: 'Vertigo',
    imagePath: '/images/maps/de_vertigo.png',
    logoPath: '/images/map-logos/vertigo.png',
    backgroundPath: '/images/map-backgrounds/vertigo.webp',
    backgroundPosition: {
      x: 50,
      y: 50,
    },
    backgroundScale: 1.2,
    resolution: 4.96,
    offset: {
      x: 3890,
      y: 3800,
    },
    includesMultipleFloors: true,
    floors: [
      {
        heightBounds: {
          min: 0,
          max: 11680,
        },
        offset: {
          x: 0.2,
          y: -57,
        },
      },
    ],
  },
};

export const getMapMetadata = (mapName: string): MapMetadata | null => {
  return mapsConfig[mapName] || null;
};

export const getAvailableMaps = (): Array<{
  name: string;
  displayName: string;
}> => {
  return Object.values(mapsConfig).map(map => ({
    name: map.name,
    displayName: map.displayName,
  }));
};
