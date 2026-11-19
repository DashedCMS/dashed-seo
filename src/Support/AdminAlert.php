<?php

declare(strict_types=1);

namespace Dashed\DashedSeo\Support;

use Filament\Actions\Action;
use Dashed\DashedCore\Classes\Mails;
use Dashed\DashedCore\Models\User;
use Illuminate\Support\Facades\Mail;
use Filament\Notifications\Notification;
use Dashed\DashedCore\Mail\NotificationMail;

/**
 * Een melding aan de beheerders: het belletje voor admin en superadmin, en
 * een mail naar de adressen voor beheerdersmeldingen. Een melding die niet
 * weg kan houdt de ronde nooit tegen.
 */
class AdminAlert
{
    public static function send(string $title, string $body, ?string $url = null, ?string $urlLabel = null): void
    {
        foreach (User::query()->whereIn('role', ['admin', 'superadmin'])->get() as $admin) {
            rescue(function () use ($admin, $title, $body, $url, $urlLabel): void {
                $notification = Notification::make()->title($title)->body($body)->warning();

                if ($url) {
                    $notification->actions([Action::make('open')->label($urlLabel ?? __('Bekijken'))->url($url)]);
                }

                $notification->sendToDatabase($admin);
            }, report: false);
        }

        foreach (Mails::getAdminNotificationEmails() as $email) {
            rescue(fn () => Mail::to($email)->send(new NotificationMail(
                $body . ($url ? "\n\n" . $url : ''),
                $title,
            )), report: false);
        }
    }
}
