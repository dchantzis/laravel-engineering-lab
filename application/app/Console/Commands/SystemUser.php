<?php

namespace App\Console\Commands;

use App\DataTransferObjects\User\User as UserData;
use App\Repositories\UserRepository;
use App\Services\UserService;

class SystemUser extends Command
{
    protected $signature = 'engineering:system-user';

    protected $description = 'Create|Update user';

    protected array $commandOptions;

    const string CREATE = 'Create';

    const string UPDATE = 'Update';

    public function __construct(private readonly UserService $userService)
    {
        $this->commandOptions = [
            self::CREATE,
            self::UPDATE,
        ];

        parent::__construct();
    }

    public function handle(): void
    {
        $this->fulfill();
    }

    public function fulfill(): array
    {
        do {

            $selection = $this->choice(
                'Select option',
                $this->commandOptions
            );

            switch ($selection) {
                case self::CREATE:

                    $this->createNewUser();
                    break;
                case self::UPDATE:
                default:

                    $this->updateUser();
                    break;
            }

        } while ($this->confirm('Show command options?'));
    }

    private function createNewUser(): void
    {
        $name = $this->ask('Enter name');
        $email = $this->ask('Enter email. Also used as username');
        $isAdmin = $this->confirm('Grant administrator privileges?', false);

        if (UserRepository::getByEmail($email, false)) {
            $this->error(sprintf(
                'User entry with email <comment>[%s]</comment> already exists.',
                $email
            ));

            return;
        }

        $user = $this->userService->store(
            new UserData(
                $name,
                $email,
                $isAdmin
            )
        );

        $this->info('New user created successfully.');
        $this->line(sprintf(
            '<comment>Name:</comment> %s',
            $user->name
        ));
        $this->line(sprintf(
            '<comment>E-Mail (Username):</comment> %s',
            $user->email
        ));
        $this->comment('Here is the user password. This is the only time it will be shown so don\'t lose it!');
        $this->line(sprintf(
            '<comment>Password:</comment> %s',
            $user->passwordUnhashed
        ));

        return;
    }

    private function updateUser(): void
    {
        $email = $this->ask('Enter email');

        $user = $this->userService->findUserByEmail($email);

        if (! $user) {
            $this->error("User with email [{$email}] does not exist.");
            return;
        }

        $name = $this->ask('Enter name', $user->name);

        $newEmail = $this->ask('Enter email', $user->email);

        $isAdmin = $this->confirm(
            'Grant administrator privileges?',
            $user->is_admin
        );

        if ($newEmail !== $user->email
            && $this->userService->findUserByEmail($newEmail)) {
            $this->error("Email [{$newEmail}] is already in use.");
            return;
        }

        $this->userService->update(
            $user,
            new UserData($name, $newEmail, $isAdmin)
        );

        $this->info('User updated successfully.');
    }
}
