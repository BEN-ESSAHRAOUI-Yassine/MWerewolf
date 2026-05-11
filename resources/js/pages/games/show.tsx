import { Head, useForm } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { show, start, advanceToDay, startVoting, resolveVotes, vote, nightAction, skipAction, end as endRoute, heartbeat, callWerewolves, callSeer, callWitch, concludeNight } from '@/routes/games';
import InputError from '@/components/input-error';

type Game = {
    id: number;
    code: string;
    status: string;
    mode: string;
    current_phase: string | null;
    active_role: string | null;
    phase_ends_at: string | null;
    round: number;
    players: GamePlayer[];
    votes: Vote[];
    events: GameEvent[];
    players_count: number;
};

type GameAction = {
    id: number;
    player_id: number;
    type: string;
    target_player_id: number | null;
    phase: string;
    metadata: Record<string, unknown> | null;
};

type GamePlayer = {
    id: number;
    name: string;
    is_host: boolean;
    is_alive: boolean;
    is_ai: boolean;
    role_id: number | null;
    role: Role | null;
    order_index: number;
    actions?: GameAction[];
};

type Role = {
    id: number;
    name: string;
    slug: string;
    faction: string;
    description: string;
};

type Vote = {
    id: number;
    voter_id: number;
    target_id: number;
    round: number;
};

type GameEvent = {
    id: number;
    type: string;
    payload: Record<string, unknown>;
    created_at: string;
};

type AvailableRole = {
    id: number;
    name: string;
    slug: string;
    faction: string;
    description: string;
    night_order: number | null;
};

type Props = {
    game: Game;
    isHost: boolean;
    myPlayer: GamePlayer | null;
    myRole: Role | null;
    availableRoles: AvailableRole[];
};

function CountdownTimer({ phaseEndsAt }: { phaseEndsAt: string | null }) {
    const [remaining, setRemaining] = useState<number>(0);

    useEffect(() => {
        if (!phaseEndsAt) return;

        function tick() {
            const diff = new Date(phaseEndsAt).getTime() - Date.now();
            setRemaining(Math.max(0, Math.floor(diff / 1000)));
        }

        tick();
        const interval = setInterval(tick, 1000);
        return () => clearInterval(interval);
    }, [phaseEndsAt]);

    if (!phaseEndsAt || remaining <= 0) return null;

    const minutes = Math.floor(remaining / 60);
    const seconds = remaining % 60;

    return (
        <span className={`font-mono text-sm ${remaining < 10 ? 'text-red-400' : 'text-stone-400'}`}>
            {minutes}:{seconds.toString().padStart(2, '0')}
        </span>
    );
}

function RoleRevealCard({ role }: { role: Role }) {
    const [revealed, setRevealed] = useState(false);

    return (
        <div
            onClick={() => setRevealed(!revealed)}
            className="cursor-pointer select-none text-center transition-all duration-500"
        >
            {!revealed ? (
                <div className="mx-auto flex h-48 w-48 flex-col items-center justify-center rounded-2xl border border-amber-700/50 bg-stone-900 shadow-lg shadow-amber-900/20">
                    <span className="text-5xl text-amber-700/60">?</span>
                    <p className="mt-3 text-xs text-stone-500">Tap to reveal</p>
                </div>
            ) : (
                <div className="mx-auto h-48 w-48 rounded-2xl border-2 shadow-lg transition-all duration-700"
                    style={{
                        borderColor: role.faction === 'werewolf' ? '#991b1b' : role.faction === 'village' ? '#166534' : '#854d0e',
                        backgroundColor: role.faction === 'werewolf' ? 'rgba(153,27,27,0.15)' : role.faction === 'village' ? 'rgba(22,101,52,0.15)' : 'rgba(133,77,14,0.15)',
                        boxShadow: role.faction === 'werewolf' ? '0 0 30px rgba(153,27,27,0.3)' : role.faction === 'village' ? '0 0 30px rgba(22,101,52,0.3)' : '0 0 30px rgba(133,77,14,0.3)',
                    }}
                >
                    <div className="flex h-full flex-col items-center justify-center p-4">
                        <span className="text-2xl font-bold text-amber-100">{role.name}</span>
                        <span className={`mt-1 text-xs uppercase tracking-widest ${
                            role.faction === 'werewolf' ? 'text-red-400' : role.faction === 'village' ? 'text-green-400' : 'text-yellow-400'
                        }`}>{role.faction}</span>
                        <p className="mt-2 text-xs text-stone-400">{role.description}</p>
                        <p className="mt-3 text-xs text-stone-600">Tap to hide</p>
                    </div>
                </div>
            )}
        </div>
    );
}

function PhaseBadge({ phase }: { phase: string | null }) {
    if (!phase) return null;
    const colors: Record<string, string> = {
        night: 'bg-indigo-900/60 text-indigo-300 border-indigo-700/50',
        day: 'bg-amber-900/60 text-amber-300 border-amber-700/50',
        voting: 'bg-red-900/60 text-red-300 border-red-700/50',
    };
    return (
        <Badge className={`px-3 py-1 text-sm capitalize ${colors[phase] || 'bg-stone-700 text-stone-300'}`}>
            {phase} Phase
        </Badge>
    );
}

function WaitingLobby({ game, isHost, availableRoles }: {
    game: Game;
    isHost: boolean;
    availableRoles: AvailableRole[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        roles: {} as Record<string, number>,
    });

    useEffect(() => {
        const defaults: Record<string, number> = {};
        availableRoles.forEach((r) => {
            defaults[r.slug] = 0;
        });
        defaults['villager'] = game.players.length;
        setData('roles', defaults);
    }, [game.players.length]);

    useEffect(() => {
        if (game.status !== 'waiting') return;

        const controller = new AbortController();

        const interval = setInterval(async () => {
            if (controller.signal.aborted) return;

            try {
                const resp = await fetch(heartbeat(game.id).url, {
                    method: 'POST',
                    signal: controller.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!resp.ok) return;

                const data = await resp.json();

                if (data.status === 'playing') {
                    controller.abort();
                    window.location.reload();
                } else if (data.players_count !== game.players.length) {
                    controller.abort();
                    window.location.reload();
                }
            } catch {
                // ignore
            }
        }, 3000);

        return () => {
            clearInterval(interval);
            controller.abort();
        };
    }, [game.status, game.players.length]);

    const totalAssigned = Object.values(data.roles).reduce((a, b) => a + b, 0);
    const currentPlayerCount = game.players.length;
    const remaining = currentPlayerCount - totalAssigned;

    function handleStart() {
        post(start(game.id));
    }

    return (
        <div className="mx-auto max-w-2xl">
            <div className="mb-8 text-center">
                <p className="text-sm text-stone-400">Room Code</p>
                <p className="font-mono text-5xl tracking-[0.3em] text-amber-100">{game.code}</p>
                <p className="mt-2 text-sm text-stone-500">Share this code for players to join</p>
            </div>

            <Card className="mb-6 border-stone-700/50 bg-stone-900/80">
                <CardHeader>
                    <CardTitle className="text-amber-100">Players ({currentPlayerCount})</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-2">
                        {game.players.map((player) => (
                            <div
                                key={player.id}
                                className="flex items-center justify-between rounded-lg bg-stone-800/50 px-3 py-2"
                            >
                                <span className="text-stone-200">
                                    {player.name}
                                    {player.is_host && (
                                        <Badge className="ml-2 bg-amber-800/50 text-amber-300 text-xs">Host</Badge>
                                    )}
                                </span>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>

            {isHost && (
                <Card className="border-stone-700/50 bg-stone-900/80">
                    <CardHeader>
                        <CardTitle className="text-amber-100">Role Configuration</CardTitle>
                        <CardDescription className="text-stone-400">
                            Assign roles to match your player count. Total: {totalAssigned} / {currentPlayerCount}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {availableRoles.map((role) => (
                                <div key={role.slug} className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm text-stone-300">{role.name}</span>
                                        <span className={`text-xs ${role.faction === 'werewolf' ? 'text-red-400' : role.faction === 'village' ? 'text-green-400' : 'text-yellow-400'}`}>
                                            ({role.faction})
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                const val = (data.roles[role.slug] || 0) - 1;
                                                setData('roles', { ...data.roles, [role.slug]: Math.max(0, val) });
                                            }}
                                            disabled={(data.roles[role.slug] || 0) <= 0}
                                            className="h-8 w-8 border-stone-600 p-0 text-stone-400"
                                        >-</Button>
                                        <span className="w-6 text-center text-amber-100">{data.roles[role.slug] || 0}</span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                if (remaining <= 0) return;
                                                const val = (data.roles[role.slug] || 0) + 1;
                                                setData('roles', { ...data.roles, [role.slug]: val });
                                            }}
                                            disabled={remaining <= 0}
                                            className="h-8 w-8 border-stone-600 p-0 text-stone-400"
                                        >+</Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                        {remaining > 0 && (
                            <p className="mt-3 text-sm text-yellow-500">
                                {remaining} player{remaining > 1 ? 's' : ''} still need{remaining === 1 ? 's' : ''} a role
                            </p>
                        )}
                        {errors.roles && <InputError message={errors.roles} className="mt-2" />}
                    </CardContent>
                    <div className="px-6 pb-6">
                        <Button
                            onClick={handleStart}
                            disabled={processing || totalAssigned !== game.players_count}
                            className="w-full bg-amber-700 text-amber-100 hover:bg-amber-600"
                        >
                            Start Game
                        </Button>
                    </div>
                </Card>
            )}
        </div>
    );
}

function PlayerView({ game, myRole, myPlayer }: { game: Game; myRole: Role | null; myPlayer: GamePlayer }) {
    const { post: votePost, processing: voteProcessing, errors: voteErrors } = useForm();

    const [selectedTarget, setSelectedTarget] = useState<number | null>(null);
    const [confirmed, setConfirmed] = useState(false);

    useEffect(() => {
        if (game.status !== 'playing') return;

        const controller = new AbortController();

        const interval = setInterval(async () => {
            if (controller.signal.aborted) return;

            try {
                const resp = await fetch(heartbeat(game.id).url, {
                    method: 'POST',
                    signal: controller.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!resp.ok) return;

                const data = await resp.json();

                if (data.phase !== game.current_phase || data.status !== game.status || data.active_role !== game.active_role) {
                    controller.abort();
                    window.location.reload();
                }
            } catch {
                // ignore
            }
        }, 2000);

        return () => {
            clearInterval(interval);
            controller.abort();
        };
    }, [game.id, game.status, game.mode, game.current_phase, game.active_role]);

    const hasVotedThisRound = game.votes?.some(v => v.voter_id === myPlayer.id && v.round === game.round);

    function handleVote(targetId: number | null) {
        if (targetId === null) {
            votePost(vote(game.id), {
                data: { target_id: myPlayer.id },
                preserveScroll: true,
            });
            return;
        }
        setConfirmed(true);
        votePost(vote(game.id), {
            data: { target_id: targetId },
            preserveScroll: true,
            onSuccess: () => {
                setConfirmed(false);
                setSelectedTarget(null);
            },
        });
    }

    const myEvents = game.events?.filter(e => {
        if (e.type === 'death' && (e.payload as any)?.player_id === myPlayer.id) return true;
        return false;
    }) || [];

    if (!myPlayer.is_alive) {
        return (
            <div className="mx-auto max-w-lg text-center">
                <div className="mb-6">
                    <span className="text-6xl">💀</span>
                    <h2 className="mt-2 text-xl font-bold text-stone-400">You have been eliminated</h2>
                    <p className="text-sm text-stone-600">Watch and enjoy the rest of the game</p>
                </div>
                <div className="rounded-xl border border-stone-700/50 bg-stone-900/60 p-4">
                    <p className="text-sm text-stone-300">Your role was:</p>
                    {myRole && <p className="mt-1 text-lg font-bold text-amber-100">{myRole.name}</p>}
                </div>
                <PlayerList players={game.players.filter(p => p.is_alive)} title="Remaining Players" />
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-lg">
            <div className="mb-6 flex flex-col items-center gap-4">
                <RoleRevealCard role={myRole!} />
            </div>

            <div className="mb-6 space-y-2">
                {myEvents.map(ev => (
                    <div key={ev.id} className="rounded-lg border border-red-900/30 bg-red-950/30 px-4 py-2 text-center text-sm text-red-300">
                        {ev.type === 'death' && 'You were killed during the night'}
                    </div>
                ))}
            </div>

            {game.current_phase === 'voting' && !hasVotedThisRound && (
                <Card className="border-stone-700/50 bg-stone-900/80">
                    <CardHeader>
                        <CardTitle className="text-lg text-amber-100">Cast Your Vote</CardTitle>
                        <CardDescription className="text-stone-400">
                            Who do you suspect?
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {game.players.filter(p => p.is_alive && p.id !== myPlayer.id).map((player) => (
                                <button
                                    key={player.id}
                                    onClick={() => { setSelectedTarget(player.id); setConfirmed(false); }}
                                    className={`w-full rounded-lg border px-4 py-3 text-left transition ${
                                        selectedTarget === player.id
                                            ? 'border-amber-600 bg-amber-900/20 text-amber-200'
                                            : 'border-stone-700/50 bg-stone-800/50 text-stone-300 hover:border-stone-600'
                                    }`}
                                >
                                    {player.name}
                                </button>
                            ))}
                        </div>
                        {voteErrors.vote && <InputError message={voteErrors.vote} className="mt-2" />}
                    </CardContent>
                    {selectedTarget && (
                        <div className="space-y-2 px-6 pb-6">
                            <Button
                                onClick={() => handleVote(selectedTarget)}
                                disabled={voteProcessing}
                                className="w-full bg-amber-700 text-amber-100 hover:bg-amber-600"
                            >
                                {confirmed ? 'Confirming...' : `Vote to Eliminate`}
                            </Button>
                            <Button
                                onClick={() => { setSelectedTarget(null); handleVote(null); }}
                                disabled={voteProcessing}
                                variant="outline"
                                className="w-full border-stone-700 text-stone-400 hover:bg-stone-800 hover:text-stone-300"
                            >
                                Abstain
                            </Button>
                        </div>
                    )}
                    {!selectedTarget && (
                        <div className="px-6 pb-6">
                            <Button
                                onClick={() => handleVote(null)}
                                disabled={voteProcessing}
                                variant="outline"
                                className="w-full border-stone-700 text-stone-400 hover:bg-stone-800 hover:text-stone-300"
                            >
                                Abstain (Skip Vote)
                            </Button>
                        </div>
                    )}
                </Card>
            )}

            {game.current_phase === 'voting' && hasVotedThisRound && (
                <div className="rounded-xl border border-stone-700/50 bg-stone-900/60 p-4 text-center">
                    <p className="text-sm text-stone-400">Your vote has been cast</p>
                    <p className="mt-1 text-xs text-stone-600">Waiting for others...</p>
                </div>
            )}

            {game.current_phase === 'night' && myRole && (
                <NightActionPanel
                    game={game}
                    myPlayer={myPlayer}
                    myRole={myRole}
                />
            )}

            {game.current_phase === 'day' && (
                <Card className="border-amber-900/30 bg-amber-950/20">
                    <CardHeader className="pb-2 text-center">
                        <CardTitle className="text-lg text-amber-300">Day Phase</CardTitle>
                        <CardDescription className="text-amber-400/60">
                            Discuss freely with other players
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="text-center">
                        <p className="text-sm text-stone-500">
                            Put down your phones and talk to each other.
                            {game.mode === 'auto_narrator' && ' Voting will begin when the timer ends.'}
                        </p>
                    </CardContent>
                </Card>
            )}

            {game.current_phase === 'night' && !myRole?.slug && (
                <div className="rounded-xl border border-indigo-900/30 bg-indigo-950/20 p-4 text-center">
                    <p className="text-sm text-indigo-300">Night Phase — Close your eyes</p>
                    <p className="mt-1 text-xs text-stone-500">Wait for the narrator to call you...</p>
                </div>
            )}

            <PlayerList players={game.players} title="All Players" />
        </div>
    );
}

function NightActionPanel({ game, myPlayer, myRole }: { game: Game; myPlayer: GamePlayer; myRole: Role }) {
    const { data, setData, post, processing } = useForm({ target_id: '' });

    const myNightActions = game.players
        .flatMap(p => p.actions || [])
        .filter(a => a.player_id === myPlayer.id && a.phase === 'night');

    const hasActed = myNightActions.length > 0;
    const isCalled = game.active_role && myRole?.slug && game.active_role === myRole.slug;

    if (!myRole?.slug) {
        return (
            <div className="mb-4 rounded-xl border border-indigo-900/30 bg-indigo-950/20 p-4 text-center">
                <p className="text-sm text-indigo-300">Night Phase — Close your eyes</p>
                <p className="mt-1 text-xs text-stone-500">Your role is not configured...</p>
            </div>
        );
    }

    if (hasActed) {
        return (
            <div className="mb-4 rounded-xl border border-indigo-900/30 bg-indigo-950/20 p-4 text-center">
                <p className="text-sm text-indigo-300">You have chosen your target</p>
                <p className="mt-1 text-xs text-stone-500">Wait for the night to end...</p>
            </div>
);
    }

    function handleSkip() {
        post(skipAction(game.id), {
            data: { player_id: myPlayer.id },
            preserveScroll: true,
        });
    }

    if (myRole.slug === 'werewolf') {
        const allWolfActions = game.players
            .flatMap(p => p.actions || [])
            .filter(a => a.phase === 'night' && a.type === 'kill');

        const wolfCount = game.players.filter(p => p.is_alive && p.role?.slug === 'werewolf').length;
        const showVoting = wolfCount > 1;

        let selectedVictim: string | null = null;
        if (showVoting && allWolfActions.length > 0) {
            const targetCounts: Record<number, number> = {};
            allWolfActions.forEach(a => {
                if (a.target_player_id) {
                    targetCounts[a.target_player_id] = (targetCounts[a.target_player_id] || 0) + 1;
                }
            });
            const maxCount = Math.max(...Object.values(targetCounts));
            const winners = Object.entries(targetCounts).filter(([, c]) => c === maxCount);
            if (winners.length === 1) {
                selectedVictim = String(winners[0][0]);
            }
        }

        return (
            <Card className="mb-4 border-indigo-700/50 bg-stone-900/80">
                <CardHeader>
                    <CardTitle className="text-sm text-indigo-300">
                        {showVoting ? `Pack Vote (${allWolfActions.length}/${wolfCount})` : 'Choose Your Target'}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-2">
                        {eligibleTargets.map(p => {
                            const myVote = allWolfActions.filter(a => a.player_id === myPlayer.id && a.target_player_id === p.id).length > 0;
                            const othersVotes = allWolfActions.filter(a => a.player_id !== myPlayer.id && a.target_player_id === p.id).length;
                            const isLeading = selectedVictim === String(p.id);

                            return (
                                <button
                                    key={p.id}
                                    onClick={() => setData('target_id', String(p.id))}
                                    className={`w-full rounded-lg border px-4 py-2 text-left text-sm transition flex items-center justify-between ${
                                        data.target_id === String(p.id)
                                            ? 'border-indigo-600 bg-indigo-900/20 text-indigo-200'
                                            : isLeading
                                            ? 'border-red-600 bg-red-900/20 text-red-200'
                                            : 'border-stone-700/50 bg-stone-800/50 text-stone-300 hover:border-stone-600'
                                    }`}
                                >
                                    <span>{p.name}</span>
                                    {othersVotes > 0 && (
                                        <span className="text-xs text-red-400">{othersVotes} vote{othersVotes > 1 ? 's' : ''}</span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                    <div className="mt-3 space-y-2">
                        {data.target_id && (
                            <Button
                                onClick={() => post(nightAction(game.id), {
                                    data: { player_id: myPlayer.id, type: 'kill', target_id: data.target_id },
                                    preserveScroll: true,
                                })}
                                disabled={processing}
                                className="w-full bg-indigo-800 text-indigo-200 hover:bg-indigo-700"
                            >
                                {showVoting ? 'Cast Vote' : 'Attack'}
                            </Button>
                        )}
                        <Button
                            onClick={handleSkip}
                            disabled={processing}
                            variant="outline"
                            className="w-full border-stone-700 text-stone-400 hover:bg-stone-800 hover:text-stone-300"
                        >
                            Skip
                        </Button>
                    </div>
                </CardContent>
            </Card>
        );
    }

    const eligibleTargets = game.players.filter(p => p.is_alive && p.id !== myPlayer.id);

    if (myRole.slug === 'seer') {
        return (
            <Card className="mb-4 border-purple-700/50 bg-stone-900/80">
                <CardHeader>
                    <CardTitle className="text-sm text-purple-300">Inspect a Player</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="space-y-2">
                        {eligibleTargets.map(p => (
                            <button
                                key={p.id}
                                onClick={() => setData('target_id', String(p.id))}
                                className={`w-full rounded-lg border px-4 py-2 text-left text-sm transition ${
                                    data.target_id === String(p.id)
                                        ? 'border-purple-600 bg-purple-900/20 text-purple-200'
                                        : 'border-stone-700/50 bg-stone-800/50 text-stone-300 hover:border-stone-600'
                                }`}
                            >
                                {p.name}
                            </button>
                        ))}
                    </div>
                    <div className="mt-3 space-y-2">
                        {data.target_id && (
                            <Button
                                onClick={() => post(nightAction(game.id), {
                                    data: { player_id: myPlayer.id, type: 'inspect', target_id: data.target_id },
                                    preserveScroll: true,
                                })}
                                disabled={processing}
                                className="w-full bg-purple-800 text-purple-200 hover:bg-purple-700"
                            >
                                Inspect
                            </Button>
                        )}
                        <Button
                            onClick={handleSkip}
                            disabled={processing}
                            variant="outline"
                            className="w-full border-stone-700 text-stone-400 hover:bg-stone-800 hover:text-stone-300"
                        >
                            Skip (Don't inspect)
                        </Button>
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (myRole.slug === 'witch') {
        return (
            <Card className="mb-4 border-stone-700/50 bg-stone-900/80">
                <CardHeader>
                    <CardTitle className="text-sm text-stone-300">Witch Potions</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                    <WitchPotion
                        game={game}
                        myPlayer={myPlayer}
                        actionType="save"
                        label="Save Potion"
                        usedLabel="Save used"
                        buttonClass="bg-green-800 text-green-200 hover:bg-green-700"
                    />
                    <WitchPotion
                        game={game}
                        myPlayer={myPlayer}
                        actionType="kill"
                        label="Kill Potion"
                        usedLabel="Kill used"
                        buttonClass="bg-red-800 text-red-200 hover:bg-red-700"
                    />
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="mb-4 rounded-xl border border-indigo-900/30 bg-indigo-950/20 p-4 text-center">
            <p className="text-sm text-indigo-300">Night Phase — Close your eyes</p>
            <p className="mt-1 text-xs text-stone-500">Wait for the narrator to call you...</p>
        </div>
    );
}

function WitchPotion({ game, myPlayer, actionType, label, usedLabel, buttonClass }: {
    game: Game;
    myPlayer: GamePlayer;
    actionType: string;
    label: string;
    usedLabel: string;
    buttonClass: string;
}) {
    const { data, setData, post, processing } = useForm({ target_id: '' });

    const myNightActions = game.players
        .flatMap(p => p.actions || [])
        .filter(a => a.player_id === myPlayer.id && a.phase === 'night');

    const usedThis = myNightActions.find(a => a.type === actionType);
    const usedOther = myNightActions.filter(a => a.type !== 'skip' && a.type !== actionType);
    const skippedThis = myNightActions.find(a => a.type === 'skip' && a.metadata?.potion === actionType);
    const anySkip = myNightActions.some(a => a.type === 'skip');

    if (skippedThis || (anySkip && !usedThis)) {
        return (
            <div className="flex items-center justify-between rounded-lg border border-stone-700/30 bg-stone-800/30 px-4 py-3">
                <span className="text-sm text-stone-400">{label}</span>
                <span className="text-xs text-stone-500">Skipped</span>
            </div>
        );
    }

    if (usedThis) {
        return (
            <div className="flex items-center justify-between rounded-lg border border-stone-700/30 bg-stone-800/30 px-4 py-3">
                <span className="text-sm text-stone-400">{label}</span>
                <span className="text-xs text-red-400">{usedLabel}</span>
            </div>
        );
    }

    if (usedOther.length > 0) {
        return (
            <div className="flex items-center justify-between rounded-lg border border-stone-700/30 bg-stone-800/30 px-4 py-3">
                <span className="text-sm text-stone-400">{label}</span>
                <span className="text-xs text-stone-500">Skipped (auto)</span>
            </div>
        );
    }

    const eligibleTargets = game.players.filter(p => p.is_alive && p.id !== myPlayer.id);

    return (
        <div>
            <div className="mb-2 space-y-1">
                {eligibleTargets.map(p => (
                    <button
                        key={p.id}
                        onClick={() => setData('target_id', String(p.id))}
                        className={`w-full rounded-lg border px-4 py-2 text-left text-sm transition ${
                            data.target_id === String(p.id)
                                ? 'border-amber-600 bg-amber-900/20 text-amber-200'
                                : 'border-stone-700/50 bg-stone-800/50 text-stone-300 hover:border-stone-600'
                        }`}
                    >
                        {p.name}
                    </button>
                ))}
            </div>
            <div className="flex gap-2">
                <Button
                    onClick={() => post(nightAction(game.id), {
                        data: { player_id: myPlayer.id, type: actionType, target_id: data.target_id },
                        preserveScroll: true,
                    })}
                    disabled={processing || !data.target_id}
                    className={`flex-1 ${buttonClass}`}
                >
                    Use
                </Button>
                <Button
                    onClick={() => post(nightAction(game.id), {
                        data: { player_id: myPlayer.id, type: 'skip', target_id: null, potion: actionType },
                        preserveScroll: true,
                    })}
                    disabled={processing}
                    variant="outline"
                    className="flex-1 border-stone-700 text-stone-400 hover:bg-stone-800 hover:text-stone-300"
                >
                    Skip
                </Button>
            </div>
        </div>
    );
}

function WitchActionPanel({ game, myPlayer, actionType, buttonClass, label }: {
    game: Game;
    myPlayer: GamePlayer;
    actionType: string;
    buttonClass: string;
    label: string;
}) {
    const { data, setData, post, processing } = useForm({ target_id: '' });

    const myNightActions = game.players
        .flatMap(p => p.actions || [])
        .filter(a => a.player_id === myPlayer.id && a.phase === 'night');

    const hasUsedThis = myNightActions.filter(a => a.type === actionType).length > 0;
    const hasSkipped = myNightActions.filter(a => a.type === 'skip').length > 0;

    if (hasUsedThis || hasSkipped) {
        return null;
    }

    const eligibleTargets = game.players.filter(p => p.is_alive && p.id !== myPlayer.id);

    return (
        <Card className="border-stone-700/50 bg-stone-900/80">
            <CardHeader>
                <CardTitle className="text-sm text-stone-300">{label}</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="space-y-2">
                    {eligibleTargets.map(p => (
                        <button
                            key={p.id}
                            onClick={() => setData('target_id', String(p.id))}
                            className={`w-full rounded-lg border px-4 py-2 text-left text-sm transition ${
                                data.target_id === String(p.id)
                                    ? 'border-amber-600 bg-amber-900/20 text-amber-200'
                                    : 'border-stone-700/50 bg-stone-800/50 text-stone-300 hover:border-stone-600'
                            }`}
                        >
                            {p.name}
                        </button>
                    ))}
                </div>
                {data.target_id && (
                    <Button
                        onClick={() => post(nightAction(game.id), {
                            data: { player_id: myPlayer.id, type: actionType, target_id: data.target_id },
                            preserveScroll: true,
                        })}
                        disabled={processing}
                        className={`mt-3 w-full ${buttonClass}`}
                    >
                        Confirm
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

function NarratorDashboard({ game }: { game: Game }) {
    const { post: startDayPost, processing: startDayProcessing } = useForm();
    const { post: startVotePost, processing: startVoteProcessing } = useForm();
    const { post: resolvePost, processing: resolveProcessing } = useForm();
    const { post: callWerewolvesPost, processing: callWerewolvesProcessing } = useForm();
    const { post: callSeerPost, processing: callSeerProcessing } = useForm();
    const { post: callWitchPost, processing: callWitchProcessing } = useForm();
    const { post: concludePost, processing: concludeProcessing } = useForm();

    const alivePlayers = game.players.filter(p => p.is_alive);
    const deadPlayers = game.players.filter(p => !p.is_alive);

    useEffect(() => {
        if (game.status !== 'playing') return;

        const controller = new AbortController();

        const interval = setInterval(async () => {
            if (controller.signal.aborted) return;

            try {
                const resp = await fetch(heartbeat(game.id).url, {
                    method: 'POST',
                    signal: controller.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!resp.ok) return;

                const data = await resp.json();

                if (data.phase !== game.current_phase || data.status !== game.status || data.active_role !== game.active_role) {
                    controller.abort();
                    window.location.reload();
                }
            } catch {
                // ignore
            }
        }, 2000);

        return () => {
            clearInterval(interval);
            controller.abort();
        };
    }, [game.id, game.status, game.mode, game.current_phase, game.active_role]);

    return (
        <div className="mx-auto max-w-4xl">
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h2 className="text-xl font-bold text-amber-100">Narrator Dashboard</h2>
                    <p className="text-sm text-stone-400">Mode: {game.mode.replace('_', ' ')}</p>
                </div>
                <div className="flex items-center gap-3">
                    <span className="text-sm text-stone-400">Round {game.round}</span>
                    <PhaseBadge phase={game.current_phase} />
                </div>
            </div>

            <div className="mb-6 grid grid-cols-3 gap-4">
                <Card className="border-stone-700/50 bg-stone-900/80">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-lg text-green-400">{alivePlayers.length}</CardTitle>
                        <CardDescription className="text-xs text-stone-500">Alive</CardDescription>
                    </CardHeader>
                </Card>
                <Card className="border-stone-700/50 bg-stone-900/80">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-lg text-red-400">{deadPlayers.length}</CardTitle>
                        <CardDescription className="text-xs text-stone-500">Dead</CardDescription>
                    </CardHeader>
                </Card>
                <Card className="border-stone-700/50 bg-stone-900/80">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-lg text-amber-400">{game.players.length}</CardTitle>
                        <CardDescription className="text-xs text-stone-500">Total</CardDescription>
                    </CardHeader>
                </Card>
            </div>

            {game.current_phase === 'night' && (
                <Card className="mb-6 border-stone-700/50 bg-stone-900/80">
                    <CardHeader>
                        <CardTitle className="text-amber-100">Story Flow - Night Phase</CardTitle>
                        <CardDescription className="text-stone-400">
                            Call each role one at a time. Active role: {game.active_role || 'none'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <Button
                            onClick={() => callWerewolvesPost(callWerewolves(game.id))}
                            disabled={callWerewolvesProcessing || game.active_role === 'werewolf'}
                            className={`${game.active_role === 'werewolf' ? 'bg-indigo-700' : 'bg-indigo-800 text-indigo-200 hover:bg-indigo-700'}`}
                        >
                            Werewolves
                        </Button>
                        <Button
                            onClick={() => callSeerPost(callSeer(game.id))}
                            disabled={callSeerProcessing || game.active_role === 'seer'}
                            className={`${game.active_role === 'seer' ? 'bg-purple-700' : 'bg-purple-800 text-purple-200 hover:bg-purple-700'}`}
                        >
                            Seer
                        </Button>
                        <Button
                            onClick={() => callWitchPost(callWitch(game.id))}
                            disabled={callWitchProcessing || game.active_role === 'witch'}
                            className={`${game.active_role === 'witch' ? 'bg-green-700' : 'bg-green-800 text-green-200 hover:bg-green-700'}`}
                        >
                            Witch
                        </Button>
                        <Button
                            onClick={() => concludePost(concludeNight(game.id))}
                            disabled={concludeProcessing}
                            className="bg-amber-700 text-amber-100 hover:bg-amber-600"
                        >
                            Conclude Night
                        </Button>
                    </CardContent>
                </Card>
            )}

            <div className="mb-6 grid gap-6 lg:grid-cols-2">
                <Card className="border-stone-700/50 bg-stone-900/80">
                    <CardHeader>
                        <CardTitle className="text-amber-100">Day / Voting</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {game.current_phase === 'day' && (
                            <Button
                                onClick={() => startVotePost(startVoting(game.id))}
                                disabled={startVoteProcessing}
                                className="w-full bg-red-700 text-red-100 hover:bg-red-600"
                            >
                                Start Voting
                            </Button>
                        )}
                        {game.current_phase === 'voting' && (
                            <Button
                                onClick={() => resolvePost(resolveVotes(game.id))}
                                disabled={resolveProcessing}
                                className="w-full bg-amber-700 text-amber-100 hover:bg-amber-600"
                            >
                                Resolve Votes
                            </Button>
                        )}
                    </CardContent>
                </Card>

                <Card className="border-stone-700/50 bg-stone-900/80">
                    <CardHeader>
                        <CardTitle className="text-amber-100">Night Actions</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="space-y-1">
                            <p className="text-xs text-stone-400">Recorded actions</p>
                            <NightActionLog game={game} type="kill" label="Wolf Attack" />
                            <NightActionLog game={game} type="save" label="Witch Save" />
                            <NightActionLog game={game} type="kill" label="Witch Kill" icon="witch_kill" />
                            <NightActionLog game={game} type="inspect" label="Seer Inspect" />
                            <NightActionLog game={game} type="skip" label="Skipped" />
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="mb-6 grid gap-6 lg:grid-cols-2">
                <Card className="border-stone-700/50 bg-stone-900/80">
                    <CardHeader>
                        <CardTitle className="text-amber-100">All Players</CardTitle>
                        <CardDescription className="text-stone-400">
                            Roles visible to narrator only
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {game.players.map(p => (
                                <div key={p.id} className="flex items-center justify-between rounded-lg bg-stone-800/50 px-3 py-2">
                                    <div className="flex items-center gap-2">
                                        <span className={`h-2 w-2 rounded-full ${p.is_alive ? 'bg-green-500' : 'bg-red-500'}`} />
                                        <span className={`text-sm ${p.is_alive ? 'text-stone-200' : 'text-stone-600 line-through'}`}>
                                            {p.name}
                                        </span>
                                        {p.is_host && <span className="text-xs text-stone-600">(Host)</span>}
                                    </div>
                                    {p.role && (
                                        <span className={`text-xs ${
                                            p.role.faction === 'werewolf' ? 'text-red-400' :
                                            p.role.faction === 'village' ? 'text-green-400' : 'text-yellow-400'
                                        }`}>
                                            {p.role.name}
                                        </span>
                                    )}
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <Card className="border-stone-700/50 bg-stone-900/80">
                    <CardHeader>
                        <CardTitle className="text-amber-100">Vote Results</CardTitle>
                        <CardDescription className="text-stone-400">
                            Current round votes
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {game.votes?.filter(v => v.round === game.round).map(vote => {
                                const voter = game.players.find(p => p.id === vote.voter_id);
                                const target = game.players.find(p => p.id === vote.target_id);
                                if (!voter || !target) return null;
                                return (
                                    <div key={vote.id} className="flex items-center justify-between rounded bg-stone-800/30 px-3 py-1.5 text-sm">
                                        <span className="text-stone-400">{voter.name}</span>
                                        <span className="text-stone-500">→</span>
                                        <span className="text-stone-300">{target.name}</span>
                                    </div>
                                );
                            })}
                            {(!game.votes || game.votes.filter(v => v.round === game.round).length === 0) && (
                                <p className="text-sm text-stone-600">No votes cast yet</p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="mb-6">
                <Card className="border-stone-700/50 bg-stone-900/80">
                    <CardHeader>
                        <CardTitle className="text-amber-100">Game Log</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="max-h-48 space-y-1 overflow-y-auto">
                            {game.events?.slice().reverse().map(ev => (
                                <div key={ev.id} className="flex items-start gap-2 rounded bg-stone-800/30 px-3 py-1.5 text-xs">
                                    <span className="shrink-0 text-stone-600">{new Date(ev.created_at).toLocaleTimeString()}</span>
                                    <span className="text-stone-400">{ev.type}</span>
                                </div>
                            ))}
                            {(!game.events || game.events.length === 0) && (
                                <p className="text-sm text-stone-600">No events yet</p>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Button
                onClick={() => resolvePost(endRoute(game.id))}
                className="w-full border-red-800/50 bg-red-950/50 text-red-400 hover:bg-red-900/50"
                variant="outline"
            >
                End Game
            </Button>
        </div>
    );
}

function NightActionLog({ game, type, label, icon }: { game: Game; type: string; label: string; icon?: string }) {
    const actions = game.players.flatMap(p =>
        p.actions?.filter(a => a.type === (icon || type) && a.phase === 'night') || []
    );

    if (actions.length === 0 && type !== 'skip') return null;

    return (
        <div className="rounded bg-stone-800/30 px-3 py-1.5 text-xs text-stone-400">
            {label}: {actions.length > 0
                ? actions.map(a => {
                    const target = game.players.find(p => p.id === a.target_player_id);
                    return target?.name || '(skipped)';
                  }).join(', ')
                : '—'}
        </div>
    );
}

function PlayerList({ players, title }: { players: GamePlayer[]; title: string }) {
    const alive = players.filter(p => p.is_alive);
    const dead = players.filter(p => !p.is_alive);

    return (
        <div className="mt-4 space-y-1">
            <p className="text-xs font-medium text-stone-500">{title}</p>
            {alive.map(p => (
                <div key={p.id} className="flex items-center gap-2 rounded bg-stone-800/30 px-3 py-1.5 text-sm text-stone-300">
                    <span className="h-1.5 w-1.5 rounded-full bg-green-500" />
                    {p.name}
                </div>
            ))}
            {dead.map(p => (
                <div key={p.id} className="flex items-center gap-2 rounded bg-stone-800/30 px-3 py-1.5 text-sm text-stone-600 line-through">
                    <span className="h-1.5 w-1.5 rounded-full bg-red-500" />
                    {p.name}
                </div>
            ))}
        </div>
    );
}

function FinishedView({ game, isHost }: { game: Game; isHost: boolean }) {
    const lastEvent = game.events?.filter(e => e.type === 'game_end').pop();
    const winner = (lastEvent?.payload as any)?.winner as string || 'unknown';

    return (
        <div className="mx-auto max-w-lg text-center">
            <div className="mb-8">
                <span className="text-6xl">
                    {winner === 'village' ? '🟢' : '🔴'}
                </span>
                <h2 className="mt-4 text-2xl font-bold text-amber-100">
                    {winner === 'village' ? 'Village Wins!' : 'Werewolves Win!'}
                </h2>
                <p className="mt-1 text-sm text-stone-400">
                    Game Over — Room {game.code}
                </p>
            </div>

            <div className="space-y-2">
                {game.players.map(p => (
                    <div key={p.id} className="flex items-center justify-between rounded-lg border border-stone-700/50 bg-stone-900/60 px-4 py-3">
                        <div className="flex items-center gap-2">
                            <span className={`h-2 w-2 rounded-full ${p.is_alive ? 'bg-green-500' : 'bg-red-500'}`} />
                            <span className={`${p.is_alive ? 'text-stone-200' : 'text-stone-600 line-through'}`}>
                                {p.name}
                            </span>
                        </div>
                        {p.role && (
                            <span className={`text-sm ${
                                p.role.faction === 'werewolf' ? 'text-red-400' :
                                p.role.faction === 'village' ? 'text-green-400' : 'text-yellow-400'
                            }`}>
                                {p.role.name}
                            </span>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function Show({ game, isHost, myPlayer, myRole, availableRoles }: Props) {
    const [phaseEndsAt, setPhaseEndsAt] = useState<string | null>(game.phase_ends_at);
    const [loaded, setLoaded] = useState(false);

    useEffect(() => {
        setPhaseEndsAt(game.phase_ends_at);
        setLoaded(true);
    }, [game.phase_ends_at]);

    useEffect(() => {
        if (!loaded || game.status !== 'playing') return;

        const controller = new AbortController();

        const interval = setInterval(async () => {
            if (controller.signal.aborted) return;

            try {
                const resp = await fetch(heartbeat(game.id).url, {
                    method: 'POST',
                    signal: controller.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });

                if (!resp.ok) return;

                const data = await resp.json();

                if (data.phase !== game.current_phase || data.status !== game.status || data.active_role !== game.active_role) {
                    controller.abort();
                    window.location.reload();
                    return;
                }

                if (data.phase_ends_at && data.phase_ends_at !== phaseEndsAt) {
                    setPhaseEndsAt(data.phase_ends_at);
                }
            } catch {
                // ignore abort/errors
            }
        }, 2000);

        return () => {
            clearInterval(interval);
            controller.abort();
        };
    }, [loaded, game.id, game.status, game.mode, game.current_phase, game.active_role, phaseEndsAt]);

    if (game.status === 'waiting') {
        return (
            <>
                <Head title={`Game ${game.code} — Lobby`} />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-y-auto p-6">
                    <WaitingLobby game={game} isHost={isHost} availableRoles={availableRoles} />
                </div>
            </>
        );
    }

    if (game.status === 'finished') {
        return (
            <>
                <Head title={`Game ${game.code} — Finished`} />
                <div className="flex h-full flex-1 flex-col gap-4 overflow-y-auto p-6">
                    <FinishedView game={game} isHost={isHost} />
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`Game ${game.code} — Playing`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-y-auto p-6">
                <div className="mx-auto mb-4 flex w-full max-w-4xl items-center justify-between">
                    <div className="flex items-center gap-3">
                        <span className="font-mono text-lg text-amber-100/60">{game.code}</span>
                        <PhaseBadge phase={game.current_phase} />
                        <CountdownTimer phaseEndsAt={phaseEndsAt} />
                    </div>
                    <span className="text-xs text-stone-500">Round {game.round}</span>
                </div>

                {isHost && game.mode === 'human_narrator' ? (
                    <NarratorDashboard game={game} />
                ) : myPlayer ? (
                    <PlayerView game={game} myRole={myRole} myPlayer={myPlayer} />
                ) : (
                    <div className="text-center text-stone-500">
                        You are not part of this game.
                    </div>
                )}
            </div>
        </>
    );
}

Show.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Games', href: '/games' },
    ],
};
