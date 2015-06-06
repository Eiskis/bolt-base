<?php
namespace Bolt\Controller\Backend;

use Bolt\AccessControl\Permissions;
use Bolt\Translation\Translator as Trans;
use Silex\ControllerCollection;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Backend controller for user maintenance routes.
 *
 * Prior to v2.3 this functionality primarily existed in the monolithic
 * Bolt\Controllers\Backend class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Users extends BackendBase
{
    protected function addRoutes(ControllerCollection $c)
    {
        $c->get('/users', 'admin')
            ->bind('users');

        $c->match('/users/edit/{id}', 'edit')
            ->assert('id', '\d*')
            ->bind('useredit');

        $c->match('/userfirst', 'first')
            ->bind('userfirst');

        $c->post('/user/{action}/{id}', 'modify')
            ->bind('useraction');

        $c->match('/profile', 'profile')
            ->bind('profile');

        $c->get('/roles', 'viewRoles')
            ->bind('roles');
    }

    /**
     * All users admin page.
     *
     * @return \Bolt\Response\BoltResponse
     */
    public function admin()
    {
        $currentuser = $this->getUser();
        $users = $this->users()->getUsers();
        $sessions = $this->authentication()->getActiveSessions();

        foreach ($users as $name => $user) {
            if (($key = array_search(Permissions::ROLE_EVERYONE, $user['roles'], true)) !== false) {
                unset($users[$name]['roles'][$key]);
            }
        }

        $context = [
            'currentuser' => $currentuser,
            'users'       => $users,
            'sessions'    => $sessions
        ];

        return $this->render('users/users.twig', $context);
    }

    /**
     * User edit route.
     *
     * @param Request $request The Symfony Request
     * @param integer $id      The user ID
     *
     * @return \Bolt\Response\BoltResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function edit(Request $request, $id)
    {
        $currentuser = $this->getUser();

        // Get the user we want to edit (if any)
        if (!empty($id)) {
            $user = $this->getUser($id);

            // Verify the current user has access to edit this user
            if (!$this->app['permissions']->isAllowedToManipulate($user, $currentuser)) {
                $this->flashes()->error(Trans::__('You do not have the right privileges to edit that user.'));

                return $this->redirectToRoute('users');
            }
        } else {
            $user = $this->users()->getEmptyUser();
        }

        $enabledoptions = [
            1 => Trans::__('page.edit-users.activated.yes'),
            0 => Trans::__('page.edit-users.activated.no')
        ];

        $roles = array_map(
            function ($role) {
                return $role['label'];
            },
            $this->app['permissions']->getDefinedRoles()
        );

        $form = $this->getUserForm($user, true);

        // New users and the current users don't need to disable themselves
        if ($currentuser['id'] != $id) {
            $form->add(
                'enabled',
                'choice',
                [
                    'choices'     => $enabledoptions,
                    'expanded'    => false,
                    'constraints' => new Assert\Choice(array_keys($enabledoptions)),
                    'label'       => Trans::__('page.edit-users.label.user-enabled'),
                ]
            );
        }

        $form
            ->add(
                'roles',
                'choice',
                [
                    'choices'  => $roles,
                    'expanded' => true,
                    'multiple' => true,
                    'label'    => Trans::__('page.edit-users.label.assigned-roles')
                ]
            )
            ->add(
                'lastseen',
                'text',
                [
                    'disabled' => true,
                    'label'    => Trans::__('page.edit-users.label.last-seen')
                ]
            )
            ->add(
                'lastip',
                'text',
                [
                    'disabled' => true,
                    'label'    => Trans::__('page.edit-users.label.last-ip')
                ]
            );

        // Set the validation
        $form = $this->setUserFormValidation($form, true);

        $form = $form->getForm();

        // Check if the form was POST-ed, and valid. If so, store the user.
        if ($request->isMethod('POST')) {
            $user = $this->validateUserForm($request, $form, false);

            $currentuser = $this->getUser();

            if ($user !== false && $user['id'] === $currentuser['id'] && $user['username'] !== $currentuser['username']) {
                // If the current user changed their own login name, the session is effectively
                // invalidated. If so, we must redirect to the login page with a flash message.
                $this->flashes()->error(Trans::__('page.edit-users.message.change-self'));

                return $this->redirectToRoute('login');
            } elseif ($user !== false) {
                // Return to the 'Edit users' screen.
                return $this->redirectToRoute('users');
            }
        }

        /** @var \Symfony\Component\Form\FormView|\Symfony\Component\Form\FormView[] $formView */
        $formView = $form->createView();

        $manipulatableRoles = $this->app['permissions']->getManipulatableRoles($currentuser);
        foreach ($formView['roles'] as $role) {
            if (!in_array($role->vars['value'], $manipulatableRoles)) {
                $role->vars['attr']['disabled'] = 'disabled';
            }
        }

        $context = [
            'kind'        => empty($id) ? 'create' : 'edit',
            'form'        => $formView,
            'note'        => '',
            'displayname' => $user['displayname'],
        ];

        return $this->render('edituser/edituser.twig', $context);
    }

    /**
     * Create the first user.
     *
     * @param Request $request The Symfony Request
     *
     * @return \Bolt\Response\BoltResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function first(Request $request)
    {
        // We should only be here for creating the first user
        if ($this->app['integritychecker']->checkUserTableIntegrity() && $this->users()->hasUsers()) {
            return $this->redirectToRoute('dashboard');
        }

        // Get and empty user array
        $user = $this->users()->getEmptyUser();

        // Add a note, if we're setting up the first user using SQLite.
        $dbdriver = $this->getOption('general/database/driver');
        if ($dbdriver === 'sqlite' || $dbdriver === 'pdo_sqlite') {
            $note = Trans::__('page.edit-users.note-sqlite');
        } else {
            $note = '';
        }

        // If we get here, chances are we don't have the tables set up, yet.
        $this->app['integritychecker']->repairTables();

        // Grant 'root' to first user by default
        $user['roles'] = [Permissions::ROLE_ROOT];

        // Get the form
        $form = $this->getUserForm($user, true);

        // Set the validation
        $form = $this->setUserFormValidation($form, true);

        /** @var \Symfony\Component\Form\Form */
        $form = $form->getForm();

        // Check if the form was POST-ed, and valid. If so, store the user.
        if ($request->isMethod('POST')) {
            if ($this->validateUserForm($request, $form, true)) {
                // To the dashboard, where 'login' will be triggered
                return $this->redirectToRoute('dashboard');
            }
        }

        $context = [
            'kind'        => 'create',
            'form'        => $form->createView(),
            'note'        => $note,
            'displayname' => $user['displayname'],
            'sitename'    => $this->getOption('general/sitename'),
        ];

        return $this->render('firstuser/firstuser.twig', $context);
    }

    /**
     * Perform modification actions on users.
     *
     * @param string  $action The action
     * @param integer $id     The user ID
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function modify($action, $id)
    {
        if (!$this->checkAntiCSRFToken()) {
            $this->flashes()->info(Trans::__('An error occurred.'));

            return $this->redirectToRoute('users');
        }
        $user = $this->getUser($id);

        if (!$user) {
            $this->flashes()->error('No such user.');

            return $this->redirectToRoute('users');
        }

        // Prevent the current user from enabling, disabling or deleting themselves
        $currentuser = $this->getUser();
        if ($currentuser['id'] == $user['id']) {
            $this->flashes()->error(Trans::__("You cannot '%s' yourself.", ['%s', $action]));

            return $this->redirectToRoute('users');
        }

        // Verify the current user has access to edit this user
        if (!$this->app['permissions']->isAllowedToManipulate($user, $currentuser)) {
            $this->flashes()->error(Trans::__('You do not have the right privileges to edit that user.'));

            return $this->redirectToRoute('users');
        }

        switch ($action) {

            case 'disable':
                if ($this->users()->setEnabled($id, 0)) {
                    $this->app['logger.system']->info("Disabled user '{$user['displayname']}'.", ['event' => 'security']);

                    $this->flashes()->info(Trans::__("User '%s' is disabled.", ['%s' => $user['displayname']]));
                } else {
                    $this->flashes()->info(Trans::__("User '%s' could not be disabled.", ['%s' => $user['displayname']]));
                }
                break;

            case 'enable':
                if ($this->users()->setEnabled($id, 1)) {
                    $this->app['logger.system']->info("Enabled user '{$user['displayname']}'.", ['event' => 'security']);
                    $this->flashes()->info(Trans::__("User '%s' is enabled.", ['%s' => $user['displayname']]));
                } else {
                    $this->flashes()->info(Trans::__("User '%s' could not be enabled.", ['%s' => $user['displayname']]));
                }
                break;

            case 'delete':

                if ($this->checkAntiCSRFToken() && $this->users()->deleteUser($id)) {
                    $this->app['logger.system']->info("Deleted user '{$user['displayname']}'.", ['event' => 'security']);
                    $this->flashes()->info(Trans::__("User '%s' is deleted.", ['%s' => $user['displayname']]));
                } else {
                    $this->flashes()->info(Trans::__("User '%s' could not be deleted.", ['%s' => $user['displayname']]));
                }
                break;

            default:
                $this->flashes()->error(Trans::__("No such action for user '%s'.", ['%s' => $user['displayname']]));

        }

        return $this->redirectToRoute('users');
    }

    /**
     * User profile page route.
     *
     * @param Request $request The Symfony Request
     *
     * @return \Bolt\Response\BoltResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function profile(Request $request)
    {
        $user = $this->getUser();

        // Get the form
        $form = $this->getUserForm($user, false);

        // Set the validation
        $form = $this->setUserFormValidation($form, false);

        /** @var \Symfony\Component\Form\Form */
        $form = $form->getForm();

        // Check if the form was POST-ed, and valid. If so, store the user.
        if ($request->isMethod('POST')) {
            $form->submit($request->get($form->getName()));

            if ($form->isValid()) {
                $user = $form->getData();

                $res = $this->users()->saveUser($user);
                $this->app['logger.system']->info(Trans::__('page.edit-users.log.user-updated', ['%user%' => $user['displayname']]), ['event' => 'security']);
                if ($res) {
                    $this->flashes()->success(Trans::__('page.edit-users.message.user-saved', ['%user%' => $user['displayname']]));
                } else {
                    $this->flashes()->error(Trans::__('page.edit-users.message.saving-user', ['%user%' => $user['displayname']]));
                }

                return $this->redirectToRoute('profile');
            }
        }

        $context = [
            'kind'        => 'profile',
            'form'        => $form->createView(),
            'note'        => '',
            'displayname' => $user['displayname'],
        ];

        return $this->render('edituser/edituser.twig', $context);
    }

    /**
     * Route to view the configured user roles.
     *
     * @return \Bolt\Response\BoltResponse
     */
    public function viewRoles()
    {
        $contenttypes = $this->getOption('contenttypes');
        $permissions = ['view', 'edit', 'create', 'publish', 'depublish', 'change-ownership'];
        $effectivePermissions = [];
        foreach ($contenttypes as $contenttype) {
            foreach ($permissions as $permission) {
                $effectivePermissions[$contenttype['slug']][$permission] =
                $this->app['permissions']->getRolesByContentTypePermission($permission, $contenttype['slug']);
            }
        }
        $globalPermissions = $this->app['permissions']->getGlobalRoles();

        $context = [
            'effective_permissions' => $effectivePermissions,
            'global_permissions'    => $globalPermissions,
        ];

        return $this->render('roles/roles.twig', $context);
    }

    /**
     * Create a user form with the form builder.
     *
     * @param array   $user
     * @param boolean $addusername
     *
     * @return \Symfony\Component\Form\FormBuilder
     */
    private function getUserForm(array $user, $addusername = false)
    {
        // Start building the form
        $form = $this->createFormBuilder('form', $user);

        // Username goes first
        if ($addusername) {
            $form->add(
                'username',
                'text',
                [
                    'constraints' => [new Assert\NotBlank(), new Assert\Length(['min' => 2, 'max' => 32])],
                    'label'       => Trans::__('page.edit-users.label.username'),
                    'attr'        => [
                        'placeholder' => Trans::__('page.edit-users.placeholder.username')
                    ]
                ]
            );
        }

        // Add the other fields
        $form
            ->add('id', 'hidden')
            ->add(
                'password',
                'password',
                [
                    'required' => false,
                    'label'    => Trans::__('page.edit-users.label.password'),
                    'attr'     => [
                        'placeholder' => Trans::__('page.edit-users.placeholder.password')
                    ]
                ]
            )
            ->add(
                'password_confirmation',
                'password',
                [
                    'required' => false,
                    'label'    => Trans::__('page.edit-users.label.password-confirm'),
                    'attr'     => [
                        'placeholder' => Trans::__('page.edit-users.placeholder.password-confirm')
                    ]
                ]
            )
            ->add(
                'email',
                'text',
                [
                    'constraints' => new Assert\Email(),
                    'label'       => Trans::__('page.edit-users.label.email'),
                    'attr'        => ['placeholder' => Trans::__('page.edit-users.placeholder.email')]
                ]
            )
            ->add(
                'displayname',
                'text',
                [
                    'constraints' => [new Assert\NotBlank(), new Assert\Length(['min' => 2, 'max' => 32])],
                    'label'       => Trans::__('page.edit-users.label.display-name'),
                    'attr'        => ['placeholder' => Trans::__('page.edit-users.placeholder.displayname')]
                ]
            );

        return $form;
    }

    /**
     * Validate the user form.
     *
     * Use a custom validator to check:
     *   * Passwords are identical
     *   * Username is unique
     *   * Email is unique
     *   * Displaynames are unique
     *
     * @param FormBuilder $form
     * @param boolean     $addusername
     *
     * @return \Symfony\Component\Form\FormBuilder
     */
    private function setUserFormValidation(FormBuilder $form, $addusername = false)
    {
        $users = $this->users();
        $form->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($addusername, $users) {
                $form = $event->getForm();
                $id = $form['id']->getData();
                $pass1 = $form['password']->getData();
                $pass2 = $form['password_confirmation']->getData();

                // If adding a new user (empty $id) or if the password is not empty (indicating we want to change it),
                // then make sure it's at least 6 characters long.
                if ((empty($id) || !empty($pass1)) && strlen($pass1) < 6) {
                    $error = new FormError(Trans::__('page.edit-users.error.password-short'));
                    $form['password']->addError($error);
                }

                // Passwords must be identical.
                if ($pass1 != $pass2) {
                    $form['password_confirmation']->addError(new FormError(Trans::__('page.edit-users.error.password-mismatch')));
                }

                if ($addusername) {
                    // Password must be different from username
                    $username = strtolower($form['username']->getData());
                    if (!empty($username) && strtolower($pass1) === $username) {
                        $form['password']->addError(new FormError(Trans::__('page.edit-users.error.password-different-username')));
                    }

                    // Password must not be contained in the display name
                    $displayname = strtolower($form['displayname']->getData());
                    if (!empty($displayname) && strrpos($displayname, strtolower($pass1)) !== false) {
                        $form['password']->addError(new FormError(Trans::__('page.edit-users.error.password-different-displayname')));
                    }

                    // Usernames must be unique.
                    if (!$users->checkAvailability('username', $form['username']->getData(), $id)) {
                        $form['username']->addError(new FormError(Trans::__('page.edit-users.error.username-used')));
                    }
                }

                // Email addresses must be unique.
                if (!$users->checkAvailability('email', $form['email']->getData(), $id)) {
                    $form['email']->addError(new FormError(Trans::__('page.edit-users.error.email-used')));
                }

                // Displaynames must be unique.
                if (!$users->checkAvailability('displayname', $form['displayname']->getData(), $id)) {
                    $form['displayname']->addError(new FormError(Trans::__('page.edit-users.error.displayname-used')));
                }
            }
        );

        return $form;
    }

    /**
     * Handle a POST from user edit or first user creation.
     *
     * @param Request $request
     * @param Form    $form      A Symfony form
     * @param boolean $firstuser If this is a first user set up
     *
     * @return array|boolean An array of user elements, otherwise false
     */
    private function validateUserForm(Request $request, Form $form, $firstuser = false)
    {
        $form->submit($request->get($form->getName()));

        if ($form->isValid()) {
            $user = $form->getData();

            if ($firstuser) {
                $user['roles'] = [Permissions::ROLE_ROOT];
            } else {
                $id = isset($user['id']) ? $user['id'] : null;
                $user['roles'] = $this->users()->filterManipulatableRoles($id, $user['roles']);
            }

            $res = $this->users()->saveUser($user);

            if (!$firstuser) {
                $this->app['logger.system']->info(Trans::__('page.edit-users.log.user-updated', ['%user%' => $user['displayname']]),
                    ['event' => 'security']);
            } else {
                $this->app['logger.system']->info(Trans::__('page.edit-users.log.user-added', ['%user%' => $user['displayname']]),
                    ['event' => 'security']);

                // Create a welcome email
                $mailhtml = $this->render(
                    'email/firstuser.twig',
                    ['sitename' => $this->getOption('general/sitename')]
                )->getContent();

                try {
                    // Send a welcome email
                    $name = $this->getOption('general/mailoptions/senderName', $this->getOption('general/sitename'));
                    $email = $this->getOption('general/mailoptions/senderMail', $user['email']);
                    $message = $this->app['mailer']
                        ->createMessage('message')
                        ->setSubject(Trans::__('New Bolt site has been set up'))
                        ->setFrom([$email         => $name])
                        ->setTo([$user['email']   => $user['displayname']])
                        ->setBody(strip_tags($mailhtml))
                        ->addPart($mailhtml, 'text/html');

                    $this->app['mailer']->send($message);
                } catch (\Exception $e) {
                    // Sending message failed. What else can we do, sending with snailmail?
                    $this->app['logger.system']->error("The 'mailoptions' need to be set in app/config/config.yml", ['event' => 'config']);
                }
            }

            if ($res) {
                $this->flashes()->success(Trans::__('page.edit-users.message.user-saved', ['%user%' => $user['displayname']]));
            } else {
                $this->flashes()->error(Trans::__('page.edit-users.message.saving-user', ['%user%' => $user['displayname']]));
            }

            return $user;
        }

        return false;
    }
}
