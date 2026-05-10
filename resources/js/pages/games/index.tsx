import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { index, create, join, show as showRoute } from '@/routes/games';
import InputError from '@/components/input-error';

type Game = {
    id: number;
    code: string;
    status: string;
    mode: string;
    players_count: number;
    created_at: string;
};

export default function GameIndex({ games }: { games: Game[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '',
    });

    function handleJoin(e: React.FormEvent) {
        e.preventDefault();
        post(join(), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    }

    return (
        <>
            <Head title="Games" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-amber-100">Games</h1>
                        <p className="mt-1 text-sm text-stone-400">Create or join a game session</p>
                    </div>
                    <Link href={create()}>
                        <Button className="bg-amber-700 text-amber-100 hover:bg-amber-600">
                            New Game
                        </Button>
                    </Link>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="border-stone-700/50 bg-stone-900/80">
                        <CardHeader>
                            <CardTitle className="text-amber-100">Join a Game</CardTitle>
                            <CardDescription className="text-stone-400">
                                Enter the 6-character room code shared by the host
                            </CardDescription>
                        </CardHeader>
                        <form onSubmit={handleJoin}>
                            <CardContent>
                                <Label htmlFor="code" className="text-stone-300">Room Code</Label>
                                <Input
                                    id="code"
                                    type="text"
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                    placeholder="ABC123"
                                    maxLength={6}
                                    className="mt-1 border-stone-600 bg-stone-800 text-amber-100 placeholder:text-stone-500"
                                />
                                <InputError message={errors.code} className="mt-1" />
                            </CardContent>
                            <CardFooter>
                                <Button
                                    type="submit"
                                    disabled={processing || data.code.length !== 6}
                                    className="bg-amber-700 text-amber-100 hover:bg-amber-600"
                                >
                                    Join Game
                                </Button>
                            </CardFooter>
                        </form>
                    </Card>

                    <Card className="border-stone-700/50 bg-stone-900/80">
                        <CardHeader>
                            <CardTitle className="text-amber-100">Your Games</CardTitle>
                            <CardDescription className="text-stone-400">
                                Games you've created or joined
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {games.length === 0 ? (
                                <p className="py-4 text-center text-stone-500">
                                    No games yet. Create one to get started!
                                </p>
                            ) : (
                                <div className="space-y-2">
                                    {games.map((game) => (
                                        <Link
                                            key={game.id}
                                            href={showRoute(game.id)}
                                            className="flex items-center justify-between rounded-lg border border-stone-700/50 bg-stone-800/50 p-3 transition hover:border-amber-700/50 hover:bg-stone-800"
                                        >
                                            <div>
                                                <span className="font-mono text-amber-100">{game.code}</span>
                                                <span className="ml-2 text-xs text-stone-500">
                                                    {game.players_count} player{game.players_count !== 1 ? 's' : ''}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <span className={`inline-block h-2 w-2 rounded-full ${
                                                    game.status === 'waiting' ? 'bg-yellow-500' :
                                                    game.status === 'playing' ? 'bg-green-500' : 'bg-stone-500'
                                                }`} />
                                                <span className="text-xs capitalize text-stone-400">{game.status}</span>
                                                <span className="text-xs capitalize text-stone-500">{game.mode.replace('_', ' ')}</span>
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

GameIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Games', href: '/games' },
    ],
};
