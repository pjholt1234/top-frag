-- Migration: Add index for efficient round-based tick data queries
-- This index significantly improves performance when loading round tick data
-- Expected improvement: ~150ms -> ~50ms per round query

-- Add composite index on (match_id, tick) for efficient range queries
-- This supports the query pattern: WHERE match_id = ? AND tick BETWEEN ? AND ?
CREATE INDEX IF NOT EXISTS idx_player_tick_match_tick 
ON player_tick_data (match_id, tick);

-- Optional: Add index on (match_id, player_id, tick) for player-specific queries
-- Uncomment if you add player-specific caching in the future
-- CREATE INDEX IF NOT EXISTS idx_player_tick_match_player_tick 
-- ON player_tick_data (match_id, player_id, tick);

-- Verify the index was created
-- SELECT * FROM information_schema.statistics 
-- WHERE table_name = 'player_tick_data' 
-- AND index_name = 'idx_player_tick_match_tick';

