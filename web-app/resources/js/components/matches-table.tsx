import React, { useState } from 'react';
import { IconChevronDown, IconChevronRight } from '@tabler/icons-react';
import { Button } from '@/components/ui/button';
import { getAdrColor } from '@/lib/utils';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface PlayerStats {
    player_name: string;
    player_kills: number;
    player_deaths: number;
    player_first_kill_differential: number;
    player_kill_death_ratio: number;
    player_adr: number;
    team: string;
}

interface MatchDetails {
    match_id: number;
    map: string;
    winning_team_score: number;
    losing_team_score: number;
    winning_team_name: string | null;
    player_won_match: boolean;
    match_type: string | null;
    match_date: string;
    player_was_participant: boolean;
}

interface Match {
    match_details: MatchDetails;
    player_stats: PlayerStats[];
}

interface MatchesTableProps {
    matches: Match[];
}

export function MatchesTable({ matches }: MatchesTableProps) {
    const [expandedRows, setExpandedRows] = useState<Set<number>>(new Set());

    const toggleRow = (matchId: number) => {
        const newExpanded = new Set(expandedRows);
        if (newExpanded.has(matchId)) {
            newExpanded.delete(matchId);
        } else {
            newExpanded.add(matchId);
        }
        setExpandedRows(newExpanded);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getTeamPlayers = (playerStats: PlayerStats[], team: string) => {
        return playerStats.filter(player => player.team === team);
    };

    const getWinningTeam = (match: Match) => {
        const { winning_team_score, losing_team_score, winning_team_name } = match.match_details;
        if (winning_team_name) return winning_team_name;
        return winning_team_score > losing_team_score ? 'Team A' : 'Team B';
    };

    return (
        <div className="space-y-4">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-12"></TableHead>
                        <TableHead>Map</TableHead>
                        <TableHead>Score</TableHead>
                        <TableHead>Result</TableHead>
                        <TableHead>Match Type</TableHead>
                        <TableHead>Date</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {matches.map((match) => {
                        const isExpanded = expandedRows.has(match.match_details.match_id);
                        const teamAPlayers = getTeamPlayers(match.player_stats, 'A');
                        const teamBPlayers = getTeamPlayers(match.player_stats, 'B');
                        const winningTeam = getWinningTeam(match);

                        return (
                            <React.Fragment key={match.match_details.match_id}>
                                <TableRow>
                                    <TableCell>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => toggleRow(match.match_details.match_id)}
                                            className="h-6 w-6 p-0"
                                        >
                                            {isExpanded ? (
                                                <IconChevronDown className="h-4 w-4" />
                                            ) : (
                                                <IconChevronRight className="h-4 w-4" />
                                            )}
                                        </Button>
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {match.match_details.map}
                                    </TableCell>
                                    <TableCell>
                                        <span className={`font-mono ${!match.match_details.player_was_participant
                                            ? '' // White/default for non-participation
                                            : match.match_details.player_won_match
                                                ? 'text-green-600 dark:text-green-400'
                                                : 'text-red-600 dark:text-red-400'
                                            }`}>
                                            {match.match_details.winning_team_score} - {match.match_details.losing_team_score}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <span className={
                                            match.match_details.player_won_match
                                                ? 'text-green-600 dark:text-green-400'
                                                : 'text-red-600 dark:text-red-400'
                                        }>
                                            {match.match_details.player_won_match ? 'Win' : 'Loss'}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        {match.match_details.match_type || 'Unknown'}
                                    </TableCell>
                                    <TableCell>
                                        {formatDate(match.match_details.match_date)}
                                    </TableCell>
                                </TableRow>
                                {isExpanded && (
                                    <TableRow>
                                        <TableCell colSpan={6} className="p-0">
                                            <div className="bg-muted/50">
                                                <div className="flex flex-col lg:flex-row">
                                                    {/* Team A */}
                                                    <div className="lg:flex-1 lg:border-r lg:border-border lg:border-b lg:border-border">
                                                        <Table className="border-0 w-full table-fixed">
                                                            <TableHeader>
                                                                <TableRow className="border-b border-border">
                                                                    <TableHead className="text-sm py-2 pl-6 pr-3 border-0 w-1/3">Player</TableHead>
                                                                    <TableHead className="text-sm py-2 px-3 border-0 w-1/6">K/D</TableHead>
                                                                    <TableHead className="text-sm py-2 px-3 border-0 w-1/6">Kills</TableHead>
                                                                    <TableHead className="text-sm py-2 px-3 border-0 w-1/6">Deaths</TableHead>
                                                                    <TableHead className="text-sm py-2 px-3 border-0 w-1/6">FK +/-</TableHead>
                                                                    <TableHead className="text-sm py-2 px-3 border-0 w-1/6">ADR</TableHead>
                                                                </TableRow>
                                                            </TableHeader>
                                                            <TableBody className="border-b border-border">
                                                                {teamAPlayers.map((player, index) => (
                                                                    <TableRow key={index} className="border-b border-border">
                                                                        <TableCell className="text-sm font-medium py-2 pl-6 pr-3 border-0">
                                                                            {player.player_name || `Player ${index + 1}`}
                                                                        </TableCell>
                                                                        <TableCell className="text-sm py-2 px-3 border-0">
                                                                            <span className={
                                                                                player.player_kill_death_ratio > 1
                                                                                    ? 'text-green-600 dark:text-green-400'
                                                                                    : player.player_kill_death_ratio < 1
                                                                                        ? 'text-red-600 dark:text-red-400'
                                                                                        : ''
                                                                            }>
                                                                                {player.player_kill_death_ratio.toFixed(2)}
                                                                            </span>
                                                                        </TableCell>
                                                                        <TableCell className="text-sm py-2 px-3 border-0">{player.player_kills}</TableCell>
                                                                        <TableCell className="text-sm py-2 px-3 border-0">{player.player_deaths}</TableCell>
                                                                        <TableCell className="text-sm py-2 px-3 border-0">
                                                                            <span className={
                                                                                player.player_first_kill_differential > 0
                                                                                    ? 'text-green-600 dark:text-green-400'
                                                                                    : player.player_first_kill_differential < 0
                                                                                        ? 'text-red-600 dark:text-red-400'
                                                                                        : ''
                                                                            }>
                                                                                {player.player_first_kill_differential > 0 ? '+' : ''}{player.player_first_kill_differential}
                                                                            </span>
                                                                        </TableCell>
                                                                        <TableCell className="text-sm font-bold py-2 px-3 border-0">
                                                                            <span className={getAdrColor(player.player_adr)}>
                                                                                {player.player_adr.toFixed(0)}
                                                                            </span>
                                                                        </TableCell>
                                                                    </TableRow>
                                                                ))}
                                                            </TableBody>
                                                        </Table>
                                                    </div>

                                                    {/* Team B */}
                                                    <div className="lg:flex-1 lg:border-b lg:border-border">
                                                        <Table className="border-0 w-full table-fixed">
                                                            <TableHeader>
                                                                <TableRow className="border-b border-border">
                                                                    <TableHead className="text-sm py-2 pl-6 pr-3 border-0 w-1/3">Player</TableHead>
                                                                    <TableHead className="text-sm py-2 px-3 border-0 w-1/6">K/D</TableHead>
                                                                    <TableHead className="text-sm py-2 px-3 border-0 w-1/6">Kills</TableHead>
                                                                    <TableHead className="text-sm py-2 px-3 border-0 w-1/6">Deaths</TableHead>
                                                                    <TableHead className="text-sm py-2 px-3 border-0 w-1/6">FK +/-</TableHead>
                                                                    <TableHead className="text-sm py-2 px-3 border-0 w-1/6">ADR</TableHead>
                                                                </TableRow>
                                                            </TableHeader>
                                                            <TableBody className="border-b border-border">
                                                                {teamBPlayers.map((player, index) => (
                                                                    <TableRow key={index} className="border-b border-border">
                                                                        <TableCell className="text-sm font-medium py-2 pl-6 pr-3 border-0">
                                                                            {player.player_name || `Player ${index + 1}`}
                                                                        </TableCell>
                                                                        <TableCell className="text-sm py-2 px-3 border-0">
                                                                            <span className={
                                                                                player.player_kill_death_ratio > 1
                                                                                    ? 'text-green-600 dark:text-green-400'
                                                                                    : player.player_kill_death_ratio < 1
                                                                                        ? 'text-red-600 dark:text-red-400'
                                                                                        : ''
                                                                            }>
                                                                                {player.player_kill_death_ratio.toFixed(2)}
                                                                            </span>
                                                                        </TableCell>
                                                                        <TableCell className="text-sm py-2 px-3 border-0">{player.player_kills}</TableCell>
                                                                        <TableCell className="text-sm py-2 px-3 border-0">{player.player_deaths}</TableCell>
                                                                        <TableCell className="text-sm py-2 px-3 border-0">
                                                                            <span className={
                                                                                player.player_first_kill_differential > 0
                                                                                    ? 'text-green-600 dark:text-green-400'
                                                                                    : player.player_first_kill_differential < 0
                                                                                        ? 'text-red-600 dark:text-red-400'
                                                                                        : ''
                                                                            }>
                                                                                {player.player_first_kill_differential > 0 ? '+' : ''}{player.player_first_kill_differential}
                                                                            </span>
                                                                        </TableCell>
                                                                        <TableCell className="text-sm font-bold py-2 px-3 border-0">
                                                                            <span className={getAdrColor(player.player_adr)}>
                                                                                {player.player_adr.toFixed(0)}
                                                                            </span>
                                                                        </TableCell>
                                                                    </TableRow>
                                                                ))}
                                                            </TableBody>
                                                        </Table>
                                                    </div>
                                                </div>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                )}
                            </React.Fragment>
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}
