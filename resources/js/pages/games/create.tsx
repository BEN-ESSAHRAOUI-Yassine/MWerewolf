import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { index, store } from '@/routes/games';

export default function CreateGame() {
    const { data, setData, post, processing, errors } = useForm({
        mode: 'human_narrator',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(store(), {
            onSuccess: () => {},
        });
    }

    return (
        <>
            <Head title="New Game" />

            <div className="flex h-full flex-1 flex-col items-center justify-center p-6">
                <Card className="w-full max-w-lg border-stone-700/50 bg-stone-900/80">
                    <CardHeader className="text-center">
                        <CardTitle className="text-2xl text-amber-100">New Game</CardTitle>
                        <CardDescription className="text-stone-400">
                            Choose how you want to orchestrate the game
                        </CardDescription>
                    </CardHeader>
                    <form onSubmit={handleSubmit}>
                        <CardContent>
                            <div className="gap-4 space-y-4">
                                <button
                                    type="button"
                                    onClick={() => setData('mode', 'human_narrator')}
                                    className={`flex w-full cursor-pointer items-start gap-4 rounded-lg border bg-stone-800/50 p-4 text-left transition ${
                                        data.mode === 'human_narrator'
                                            ? 'border-amber-600 bg-stone-800'
                                            : 'border-stone-700/50 hover:border-amber-700/50'
                                    }`}
                                >
                                    <div className={`mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 ${
                                        data.mode === 'human_narrator' ? 'border-amber-500' : 'border-stone-500'
                                    }`}>
                                        {data.mode === 'human_narrator' && (
                                            <div className="h-2.5 w-2.5 rounded-full bg-amber-500" />
                                        )}
                                    </div>
                                    <div>
                                        <p className="font-medium text-amber-100">Human Narrator</p>
                                        <p className="mt-1 text-sm text-stone-400">
                                            A real person controls the pacing. The app handles role distribution,
                                            hidden info, and vote tracking. Best for immersive sessions with an active narrator.
                                        </p>
                                    </div>
                                </button>

                                <button
                                    type="button"
                                    onClick={() => setData('mode', 'auto_narrator')}
                                    className={`flex w-full cursor-pointer items-start gap-4 rounded-lg border bg-stone-800/50 p-4 text-left transition ${
                                        data.mode === 'auto_narrator'
                                            ? 'border-amber-600 bg-stone-800'
                                            : 'border-stone-700/50 hover:border-amber-700/50'
                                    }`}
                                >
                                    <div className={`mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 ${
                                        data.mode === 'auto_narrator' ? 'border-amber-500' : 'border-stone-500'
                                    }`}>
                                        {data.mode === 'auto_narrator' && (
                                            <div className="h-2.5 w-2.5 rounded-full bg-amber-500" />
                                        )}
                                    </div>
                                    <div>
                                        <p className="font-medium text-amber-100">App Narrator</p>
                                        <p className="mt-1 text-sm text-stone-400">
                                            The app guides the game with automated phase transitions and timers.
                                            Great for small groups or quick setup. Real-life discussion remains the focus.
                                        </p>
                                    </div>
                                </button>
                            </div>
                            {errors.mode && (
                                <p className="mt-2 text-sm text-red-400">{errors.mode}</p>
                            )}
                        </CardContent>
                        <CardFooter className="flex justify-between">
                            <a
                                href={index()}
                                className="text-sm text-stone-400 transition hover:text-stone-300"
                            >
                                Back
                            </a>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="bg-amber-700 text-amber-100 hover:bg-amber-600"
                            >
                                Create Game
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </>
    );
}

CreateGame.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Games', href: '/games' },
        { title: 'New Game', href: '/games/create' },
    ],
};
