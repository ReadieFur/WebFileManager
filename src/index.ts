import { Main, IXHRReject, IServerErrorResponse } from './assets/js/main.js';

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
            this.VerifyToken().then((valid) =>
            {
                this.SetTab(valid ? 'accountTab' : 'logInTab');
                Main.XHR<IAccountDataResponse>(
                {
                    url: `${Main.WEB_ROOT}/api/v1/account/`,
                    method: 'POST',
                    data:
                    {
                        method: 'get_account_details',
                        id: this.userID,
                        token: this.userToken,
                        uid: this.userID
                    },
                    headers:
                    {
                        "Content-Type": "application/x-www-form-urlencoded"
                    }
                })
                .then((response) =>
                {
                    this.elements.accountTab.username.value = response.response.username;
                })
                .catch((error: IXHRReject<IServerErrorResponse>) =>
                {
                    Main.Alert(Main.GetErrorMessage(error.error));
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

        const xhrResponse = await Main.XHR<ILogInRespose>(
        {
            url: `${Main.WEB_ROOT}/api/v1/account/`,
            method: 'POST',
            data:
            {
                method: 'log_in',
                username: username,
                password: password
            },
            headers:
            {
                "Content-Type": "application/x-www-form-urlencoded"
            }
        })
        .catch((error: IXHRReject<IServerErrorResponse>) =>
        {
            return error;
        });

        if ((xhrResponse.response as IServerErrorResponse).error !== undefined)
        {
            Main.Alert(Main.GetErrorMessage((xhrResponse.response as IServerErrorResponse).error));
            return;
        }

        const response = xhrResponse.response as ILogInRespose;
        
        this.userID = response.uid;
        this.userToken = response.token;

        Main.SetCache('uid', response.uid, this.COOKIE_TIME, Main.WEB_ROOT + '/');
        Main.SetCache('token', response.token, this.COOKIE_TIME, Main.WEB_ROOT + '/');

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

        const xhrResponse = await Main.XHR<object>(
        {
            url: `${Main.WEB_ROOT}/api/v1/account/`,
            method: 'POST',
            data:
            {
                method: 'update_account',
                uid: this.userID,
                token: this.userToken,
                old_password: currentPassword,
                new_password: newPassword
            },
            headers:
            {
                "Content-Type": "application/x-www-form-urlencoded"
            }
        })
        .catch((error: IXHRReject<IServerErrorResponse>) =>
        {
            return error;
        });

        if ((xhrResponse.response as IServerErrorResponse).error !== undefined)
        {
            Main.Alert(Main.GetErrorMessage((xhrResponse.response as IServerErrorResponse).error));
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

    private async VerifyToken(): Promise<boolean>
    {
        const xhrResponse = await Main.XHR<object>(
        {
            url: `${Main.WEB_ROOT}/api/v1/account/`,
            method: 'POST',
            data:
            {
                method: 'verify_token',
                id: this.userID,
                token: this.userToken
            },
            headers:
            {
                "Content-Type": "application/x-www-form-urlencoded"
            }
        })
        .catch((error: IXHRReject<IServerErrorResponse>) =>
        {
            return error;
        });

        return (xhrResponse.response as IServerErrorResponse).error === undefined;
    }
}
new Index();

interface ILogInRespose
{
    uid: string;
    token: string;
}

interface IAccountDataResponse
{
    username: string;
    admin: 0 | 1;
}