import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { create, index } from '@/routes/games';
import { dashboard } from '@/routes';

type GameSummary = {
    id: number;
    code: string;
    status: string;
    mode: string;
    players_count: number;
    created_at: string;
};

export default function Dashboard({ activeGames, recentGames }: {
    activeGames: GameSummary[];
    recentGames: GameSummary[];
}) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-amber-100">Werewolf</h1>
                        <p className="mt-1 text-sm text-stone-400">Social deduction companion</p>
                    </div>
                    <div className="flex gap-3">
                        <Link href={index()}>
                            <Button variant="outline" className="border-stone-700 text-stone-300 hover:bg-stone-800 hover:text-amber-100">
                                Browse Games
                            </Button>
                        </Link>
                        <Link href={create()}>
                            <Button className="bg-amber-700 text-amber-100 hover:bg-amber-600">
                                New Game
                            </Button>
                        </Link>
                    </div>
                </div>

                {activeGames.length > 0 && (
                    <Card className="border-stone-700/50 bg-stone-900/80">
                        <CardHeader>
                            <CardTitle className="text-amber-100">Active Games</CardTitle>
                            <CardDescription className="text-stone-400">Games in progress</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {activeGames.map((game) => (
                                    <Link
                                        key={game.id}
                                        href={`/games/${game.id}`}
                                        className="flex items-center justify-between rounded-lg border border-green-800/30 bg-stone-800/50 p-3 transition hover:border-green-700/50"
                                    >
                                        <span className="font-mono text-amber-100">{game.code}</span>
                                        <div className="flex items-center gap-3">
                                            <span className="text-sm text-stone-400">{game.players_count} players</span>
                                            <span className="inline-block h-2 w-2 rounded-full bg-green-500" />
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-6 md:grid-cols-3">
                    <Link href={create({ query: { mode: 'human_narrator' } })}>
                        <Card className="h-full cursor-pointer border-stone-700/50 bg-stone-900/80 transition hover:border-amber-700/50 hover:bg-stone-800">
                            <CardHeader>
                                <CardTitle className="text-lg text-amber-100">Human Narrator</CardTitle>
                                <CardDescription className="text-stone-400">
                                    You control the pacing. App handles roles, votes, and hidden info.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-stone-500">Best for immersive sessions with an active storyteller</p>
                            </CardContent>
                        </Card>
                    </Link>
                    <Link href={create({ query: { mode: 'auto_narrator' } })}>
                        <Card className="h-full cursor-pointer border-stone-700/50 bg-stone-900/80 transition hover:border-amber-700/50 hover:bg-stone-800">
                            <CardHeader>
                                <CardTitle className="text-lg text-amber-100">App Narrator</CardTitle>
                                <CardDescription className="text-stone-400">
                                    App guides the game with automated phases and timers.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-stone-500">Quick setup for small groups or beginners</p>
                            </CardContent>
                        </Card>
                    </Link>
                    <Link href={index()}>
                        <Card className="h-full cursor-pointer border-stone-700/50 bg-stone-900/80 transition hover:border-amber-700/50 hover:bg-stone-800">
                            <CardHeader>
                                <CardTitle className="text-lg text-amber-100">Join Game</CardTitle>
                                <CardDescription className="text-stone-400">
                                    Enter a room code to join an existing session.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-stone-500">Connect with friends using their 6-character code</p>
                            </CardContent>
                        </Card>
                    </Link>
                </div>

                {recentGames.length > 0 && (
                    <Card className="border-stone-700/50 bg-stone-900/80">
                        <CardHeader>
                            <CardTitle className="text-amber-100">Recent Games</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {recentGames.map((game) => (
                                    <Link
                                        key={game.id}
                                        href={`/games/${game.id}`}
                                        className="flex items-center justify-between rounded-lg bg-stone-800/30 px-3 py-2 transition hover:bg-stone-800"
                                    >
                                        <span className="font-mono text-sm text-stone-400">{game.code}</span>
                                        <span className="text-xs capitalize text-stone-500">{game.status} · {game.mode.replace('_', ' ')}</span>
                                    </Link>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {activeGames.length === 0 && recentGames.length === 0 && (
                    <Card className="border-stone-700/50 bg-stone-900/80">
                        <CardContent className="py-12 text-center">
                            <p className="text-lg text-stone-500">No games yet</p>
                            <p className="mt-1 text-sm text-stone-600">Create a new game or join one with a room code</p>
                            <Link href={create()} className="mt-4 inline-block">
                                <Button className="bg-amber-700 text-amber-100 hover:bg-amber-600">
                                    Create Your First Game
                                </Button>
                            </Link>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
    ],
};
