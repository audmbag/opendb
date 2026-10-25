<?php

namespace App\Http\Controllers;

use App\Http\Requests\MailRecipientRequest;
use App\Models\MailRecipient;
use App\Repository\MailRecipientRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MailRecipientController extends Controller
{
    public function index(Request $request, MailRecipientRepository $mailRecipientRepository): JsonResponse
    {
        return response()->json([
            'data' => $mailRecipientRepository->list($request->user()),
        ]);
    }

    public function store(MailRecipientRequest $request, MailRecipientRepository $mailRecipientRepository): JsonResponse
    {
        try {
            $recipient = $mailRecipientRepository->add($request->user(), $request->validated('email'), $request->validated('name'));
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['email' => $e->getMessage()]);
        }

        return response()->json(['data' => $recipient], 201);
    }

    public function destroy(Request $request, MailRecipient $mailRecipient): JsonResponse
    {
        abort_unless($mailRecipient->user_id === $request->user()->id, 404);

        $mailRecipient->delete();

        return response()->json(['success' => true]);
    }
}
