import { Main, IXHRReject } from './assets/js/main.js';
import { Account } from './assets/js/account.js';

class Index
{
    private readonly COOKIE_TIME = 3 * 30; //3 months.

    private elements:
    {
        loading: HTMLParagraphElement;
        logInTab:
        {
            form: HTMLFormElement;
            username: HTMLInputElement;
            password: HTMLInputElement;
            logInButton: HTMLButtonElement;
        },
        accountTab:
        {
            form: HTMLFormElement;
            username: HTMLInputElement;
            currentPassword: HTMLInputElement;
            newPassword: HTMLInputElement;
            confirmPassword: HTMLInputElement;
            updateAccountButton: HTMLButtonElement;
            logOutButton: HTMLButtonElement;
        }
    };
    private userID?: string;
    private userToken?: string;

    constructor()
    {
        new Main();

        this.elements =
        {
            loading: Main.GetElement("#loading"),
            logInTab:
            {
                form: Main.GetElement("#logInTab"),
                username: Main.GetElement("#logInTab .username"),
                password: Main.GetElement("#logInTab .password"),
                logInButton: Main.GetElement("#logInTab .logInButton")
            },
            accountTab:
            {
                form: Main.GetElement("#accountTab"),
                username: Main.GetElement("#accountTab .username"),
                currentPassword: Main.GetElement("#accountTab .currentPassword"),
                newPassword: Main.GetElement("#accountTab .newPassword"),
                confirmPassword: Main.GetElement("#accountTab .confirmPassword"),
                updateAccountButton: Main.GetElement("#accountTab .updateAccountButton"),
                logOutButton: Main.GetElement("#accountTab .logOutButton")
            }
        };
        Main.PreventFormSubmission(this.elements.logInTab.form);
        Main.PreventFormSubmission(this.elements.accountTab.form);

        this.userID = Main.RetreiveCache('uid');
        this.userToken = Main.RetreiveCache('token');

        if (this.userID === undefined || this.userToken === undefined)
        {
            this.SetTab('logInTab');
        }
        else
        {
            Account.VerifyToken(
                this.userID,
                this.userToken
            )
            .then((tokenResult) =>
            {
                this.SetTab(tokenResult.error === false ? 'accountTab' : 'logInTab');
                Account.GetAccountDetails(
                    this.userID!,
                    this.userToken!,
                    this.userID!
                )
                .then((detailsResult) =>
                {
                    if (detailsResult.error !== false)
                    {
                        Main.Alert(Main.GetErrorMessage(detailsResult.error));
                    }
                    else
                    {
                        this.elements.accountTab.username.value = detailsResult.data!.username;
                    }
                });
            });
        }

        this.elements.logInTab.logInButton.addEventListener('click', () => { this.LogIn(); });
        this.elements.accountTab.updateAccountButton.addEventListener('click', () => { this.UpdateAccount(); });
        this.elements.accountTab.logOutButton.addEventListener('click', () => { this.LogOut(); });
    }

    private SetTab(tab: "logInTab" | "accountTab"): void
    {
        this.elements.loading.style.display = 'none';

        this.elements.logInTab.username.value = "";
        this.elements.logInTab.password.value = "";

        this.elements.accountTab.username.value = "";
        this.elements.accountTab.currentPassword.value = "";
        this.elements.accountTab.newPassword.value = "";

        switch (tab)
        {
            case "logInTab":
                this.elements.logInTab.form.style.display = 'block';
                this.elements.accountTab.form.style.display = 'none';
                break;
            case "accountTab":
                this.elements.logInTab.form.style.display = 'none';
                this.elements.accountTab.form.style.display = 'block';
                break;
        }
    }

    private async LogIn(): Promise<void>
    {
        const username = this.elements.logInTab.username.value;
        const password = this.elements.logInTab.password.value;

        const logInResponse = await Account.LogIn(username, password);
        if (logInResponse.error !== false)
        {
            Main.Alert(Main.GetErrorMessage(logInResponse.error));
            return;
        }

        this.userID = logInResponse.data!.uid;
        this.userToken = logInResponse.data!.token;

        Main.SetCache('uid', logInResponse.data!.uid, this.COOKIE_TIME, Main.WEB_ROOT + '/');
        Main.SetCache('token', logInResponse.data!.token, this.COOKIE_TIME, Main.WEB_ROOT + '/');

        this.SetTab('accountTab');
        this.elements.accountTab.username.value = username;
    }

    private async UpdateAccount(): Promise<void>
    {
        // const username = this.elements.accountTab.username.value;
        const currentPassword = this.elements.accountTab.currentPassword.value;
        const newPassword = this.elements.accountTab.newPassword.value;
        const confirmPassword = this.elements.accountTab.confirmPassword.value;

        if (newPassword !== confirmPassword)
        {
            Main.Alert('Passwords do not match.');
            return;
        }

        const updateResponse = await Account.UpdateAccount(
            this.userID!,
            this.userToken!,
            this.userID!,
            currentPassword,
            newPassword,
            null
        );
        if (updateResponse.error !== false)
        {
            Main.Alert(updateResponse.error);
            return;
        }

        Main.Alert('Account updated.');

        this.elements.accountTab.currentPassword.value = '';
        this.elements.accountTab.newPassword.value = '';
        this.elements.accountTab.confirmPassword.value = '';
    }

    private LogOut(): void
    {
        this.SetTab('logInTab');

        Main.SetCache('uid', '', 0, Main.WEB_ROOT + '/');
        Main.SetCache('token', '', 0, Main.WEB_ROOT + '/');
        this.userID = undefined;
        this.userToken = undefined;
    }
}
new Index();