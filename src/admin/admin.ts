import { Main, IXHRReject, IServerErrorResponse } from "../assets/js/main.js";
import { Account } from "../assets/js/account.js";

class Admin
{
    private userID: string;
    private userToken: string;
    private elements:
    {
        tabs:
        {
            buttonsContainer: HTMLDivElement;
            account:
            {
                tabButton: HTMLButtonElement;
                container: HTMLFormElement;
                username: HTMLInputElement;
                password: HTMLInputElement;
                passwordConfirm: HTMLInputElement;
                admin: HTMLInputElement;
                submit: HTMLButtonElement;
                mode: "create" | "update";
                activeAccountID?: string;
            },
            paths:
            {
                tabButton: HTMLButtonElement;
                container: HTMLFormElement;
                webPath: HTMLInputElement;
                localPath: HTMLInputElement;
                submit: HTMLButtonElement;
                mode: "add" | "update";
                activePath?: string;
            }
        },
        table:
        {
            thBody: HTMLTableSectionElement;
            tdBody: HTMLTableSectionElement;
        }
    }

    constructor()
    {
        new Main();

        this.userID = Main.RetreiveCache("uid")??"";
        this.userToken = Main.RetreiveCache("token")??"";
        this.elements =
        {
            tabs:
            {
                buttonsContainer: Main.GetElement(".tabButtons"),
                account:
                {
                    tabButton: Main.GetElement("#accountsTabButton"),
                    container: Main.GetElement("#accountsTab"),
                    username: Main.GetElement("#username"),
                    password: Main.GetElement("#password"),
                    passwordConfirm: Main.GetElement("#passwordConfirm"),
                    admin: Main.GetElement("#admin"),
                    submit: Main.GetElement("#accountSubmit"),
                    mode: "create"
                },
                paths:
                {
                    tabButton: Main.GetElement("#pathsTabButton"),
                    container: Main.GetElement("#pathsTab"),
                    webPath: Main.GetElement("#webPath"),
                    localPath: Main.GetElement("#localPath"),
                    submit: Main.GetElement("#pathSubmit"),
                    mode: "add"
                }
            },
            table:
            {
                thBody: Main.GetElement("#thBody"),
                tdBody: Main.GetElement("#tdBody")
            }
        }

        Account.GetAccountDetails(
            this.userID,
            this.userToken,
            this.userID
        )
        .then((result) =>
        {
            if (result.error !== false)
            {
                Main.Alert(Main.GetErrorMessage(result.error));
                return;
            }
            else
            {
                this.elements.tabs.buttonsContainer.style.display = "block";
                this.LoadAccounts();
            }
        });

        Main.PreventFormSubmission(this.elements.tabs.account.container);
        Main.PreventFormSubmission(this.elements.tabs.paths.container);
        this.elements.tabs.account.tabButton.addEventListener("click", () => { this.LoadAccounts(); });
        this.elements.tabs.paths.tabButton.addEventListener("click", () => { this.LoadPaths(); });
    }

    private SetTab(tab: "accounts" | "paths"): void
    {
        this.elements.tabs.account.tabButton.classList.remove("active");
        this.elements.tabs.paths.tabButton.classList.remove("active");

        this.elements.tabs.account.container.style.display = "none";
        this.elements.tabs.paths.container.style.display = "none";
        
        switch (tab)
        {
            case "accounts":
                this.elements.tabs.account.tabButton.classList.add("active");
                this.elements.tabs.account.container.style.display = "block";
                this.ResetAccountForm();
                break;
            case "paths":
                this.elements.tabs.paths.tabButton.classList.add("active");
                this.elements.tabs.paths.container.style.display = "block";
                this.ResetPathForm();
                break;
        }
    }

    private async LoadAccounts(): Promise<void>
    {
        const accountsResponse = await Account.GetAllAccounts(this.userID, this.userToken);
        if (accountsResponse.error !== false)
        {
            Main.Alert(Main.GetErrorMessage(accountsResponse.error));
            return;
        }

        this.SetTab("accounts");

        //Clear the table.
        this.elements.table.thBody.innerHTML = "";
        this.elements.table.tdBody.innerHTML = "";
        
        //#region Create table headers.
        const thr = document.createElement("tr");
        
        const th1 = document.createElement("th");
        th1.innerHTML = "Username";
        thr.appendChild(th1);

        const th2 = document.createElement("th");
        th2.innerHTML = "Admin";
        thr.appendChild(th2);

        const th3 = document.createElement("th");
        th3.innerHTML = "Options";
        thr.appendChild(th3);

        this.elements.table.thBody.appendChild(thr);
        //#endregion

        //#region Create table rows.
        const accounts = accountsResponse.data!.accounts;
        //Sort the accounts by username.
        accounts.sort((a, b) =>
        {
            if (a.username < b.username) { return -1; }
            else if (a.username > b.username) { return 1; }
            else { return 0; }
        });
        //Then sort the accounts by admin.
        accounts.sort((a, b) =>
        {
            if (a.admin === b.admin) { return 0; }
            else if (a.admin == 1) { return -1; }
            else { return 1; }
        });

        for (let i = 0; i < accounts.length; i++)
        {
            const account = accounts[i];

            const isAdminAccount = account.username === "admin";

            const tr = document.createElement("tr");
            tr.classList.add("listItem", "selectable");

            const td1 = document.createElement("td");
            const p = document.createElement("p");
            p.innerHTML = account.username;
            td1.appendChild(p);
            tr.appendChild(td1);

            const td2 = document.createElement("td");
            const label = document.createElement("label");
            label.classList.add("checkboxContainer");
            const input = document.createElement("input");
            input.type = "checkbox";
            input.checked = account.admin == 1 ? true : false;
            input.disabled = true;
            label.appendChild(input);
            const span = document.createElement("span");
            span.classList.add("checkmark");
            label.appendChild(span);
            td2.appendChild(label);
            tr.appendChild(td2);

            const td3 = document.createElement("td");
            const button1 = document.createElement("button");
            button1.classList.add("red");
            button1.innerHTML = "Delete";
            button1.ondblclick = async () =>
            {
                const deleteResult = await Account.DeleteAccount(
                    Main.RetreiveCache("uid")??"",
                    Main.RetreiveCache("token")??"",
                    account.uid
                );
                if (typeof deleteResult.error === "string")
                {
                    Main.Alert(Main.GetErrorMessage(deleteResult.error));
                    return;
                }

                this.LoadAccounts();
            };

            if (!isAdminAccount) { td3.appendChild(button1); }

            tr.appendChild(td3);

            tr.onclick = (e) =>
            {
                if (e.target !== button1)
                {
                    this.elements.tabs.account.activeAccountID = account.uid;
                    this.elements.tabs.account.username.value = account.username;
                    this.elements.tabs.account.password.value = "";
                    this.elements.tabs.account.passwordConfirm.value = "";
                    this.elements.tabs.account.admin.checked = account.admin == 1 ? true : false;
                    this.elements.tabs.account.admin.disabled = isAdminAccount;
                    this.elements.tabs.account.submit.innerHTML = "Update";
                    this.elements.tabs.account.mode = "update";
                    this.elements.tabs.account.username.oninput = (e) => { this.ResetAccountForm((<InputEvent>e).data??""); };
                    this.elements.tabs.account.container.onsubmit = (e) => { this.SaveAccount(); };
                }
            };

            this.elements.table.tdBody.appendChild(tr);
        }
    }

    private ResetAccountForm(usernameText = ""): void
    {
        this.elements.tabs.account.username.value = usernameText;
        this.elements.tabs.account.password.value = "";
        this.elements.tabs.account.passwordConfirm.value = "";
        this.elements.tabs.account.admin.checked = false;
        this.elements.tabs.account.admin.disabled = false;
        this.elements.tabs.account.submit.innerHTML = "Create";
        this.elements.tabs.account.mode = "create";
        this.elements.tabs.account.activeAccountID = undefined;
        this.elements.tabs.account.username.oninput = () => {};
        this.elements.tabs.account.container.onsubmit = (e) => { this.SaveAccount(); };
    }

    private ResetPathForm(): void
    {
        this.elements.tabs.paths.localPath.value = "";
        this.elements.tabs.paths.webPath.value = "";
        this.elements.tabs.paths.submit.innerHTML = "Add";
        this.elements.tabs.paths.mode = "add";
        this.elements.tabs.paths.activePath = undefined;
        this.elements.tabs.paths.container.onsubmit = (e) => { this.SavePath(); };
    }

    private async LoadPaths(): Promise<void>
    {
        const pathsResponse = await this.GetRoots();
        if (typeof pathsResponse === "string")
        {
            Main.Alert(Main.GetErrorMessage(pathsResponse));
            return;
        }

        this.SetTab("paths");

        //Clear the table.
        this.elements.table.thBody.innerHTML = "";
        this.elements.table.tdBody.innerHTML = "";

        //#region Create table headers.
        const thr = document.createElement("tr");
        
        const th1 = document.createElement("th");
        th1.innerHTML = "Web Path";
        thr.appendChild(th1);

        const th2 = document.createElement("th");
        th2.innerHTML = "Local Path";
        thr.appendChild(th2);

        const th3 = document.createElement("th");
        th3.innerHTML = "Options";
        thr.appendChild(th3);

        this.elements.table.thBody.appendChild(thr);
        //#endregion

        //#region Create table rows.
        for (let i = 0; i < pathsResponse.paths.length; i++)
        {
            const path = pathsResponse.paths[i];
            const tr = document.createElement("tr");
            tr.classList.add("listItem", "selectable");

            const td1 = document.createElement("td");
            const p = document.createElement("p");
            p.innerHTML = path.web_path;
            td1.appendChild(p);
            tr.appendChild(td1);

            const td2 = document.createElement("td");
            const p2 = document.createElement("p");
            p2.innerHTML = path.local_path;
            td2.appendChild(p2);
            tr.appendChild(td2);

            const td3 = document.createElement("td");
            const button1 = document.createElement("button");
            button1.classList.add("red");
            button1.innerHTML = "Delete";
            button1.ondblclick = async () =>
            {
                const deleteResponse = await this.SaveRoot(
                    "delete_root",
                    {
                        web_path: path.web_path
                    }
                );
                if (typeof deleteResponse === "string")
                {
                    Main.Alert(Main.GetErrorMessage(deleteResponse));
                    return;
                }

                this.LoadPaths();
            };
            td3.appendChild(button1);
            tr.appendChild(td3);

            tr.onclick = (e) =>
            {
                if (e.target !== button1)
                {
                    this.elements.tabs.paths.activePath = path.web_path;
                    this.elements.tabs.paths.webPath.value = path.web_path;
                    this.elements.tabs.paths.localPath.value = path.local_path;
                    this.elements.tabs.paths.submit.innerHTML = "Update";
                    this.elements.tabs.paths.mode = "update";
                    this.elements.tabs.paths.container.onsubmit = (e) => { this.SavePath(); };
                }
            };

            this.elements.table.tdBody.appendChild(tr);
        }
    }

    private async GetRoots(): Promise<string | IRootsResponse>
    {
        const directoryResponse = await Main.XHR<IRootsResponse>(
        {
            url: `${Main.WEB_ROOT}/api/v1/directory/`,
            method: "POST",
            data:
            {
                id: Main.RetreiveCache("uid"),
                token: Main.RetreiveCache("token"),
                method: "get_roots"
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

        return (directoryResponse.response as IServerErrorResponse).error !== undefined ?
            (directoryResponse.response as IServerErrorResponse).error :
            (directoryResponse.response as IRootsResponse);
    }

    private async SaveAccount(): Promise<void>
    {
        const username = this.elements.tabs.account.username.value;
        const password = this.elements.tabs.account.password.value;
        const passwordConfirm = this.elements.tabs.account.passwordConfirm.value;
        const admin = this.elements.tabs.account.admin.checked ? 1 : 0;

        if (this.elements.tabs.account.mode === "create")
        {
            if (password !== passwordConfirm || /\S/.test(password) === false)
            {
                Main.Alert("Passwords do not match.");
                return;
            }

            const accountResponse = await Account.CreateAcount(
                Main.RetreiveCache("uid")??"",
                Main.RetreiveCache("token")??"",
                username,
                password,
                admin
            );
            if (typeof accountResponse.error === "string")
            {
                Main.Alert(Main.GetErrorMessage(accountResponse.error));
                return;
            }

            this.LoadAccounts();
        }
        else
        {
            if (/\S/.test(password) === true && password !== passwordConfirm)
            {
                Main.Alert("Passwords do not match.");
                return;
            }

            const accountResponse = await Account.UpdateAccount(
                Main.RetreiveCache("uid")??"",
                Main.RetreiveCache("token")??"",
                this.elements.tabs.account.activeAccountID!,
                null,
                password,
                admin
            );
            if (typeof accountResponse.error === "string")
            {
                Main.Alert(Main.GetErrorMessage(accountResponse.error));
                return;
            }

            this.LoadAccounts();
        }
    }

    private async SavePath(): Promise<void>
    {
        const localPath = this.elements.tabs.paths.localPath.value;
        const webPath = this.elements.tabs.paths.webPath.value;

        if (this.elements.tabs.paths.mode === "add")
        {
            const pathResponse = await this.SaveRoot(
                "add_root",
                {
                    web_path: webPath,
                    local_path: localPath
                }
            );
            if (typeof pathResponse === "string")
            {
                Main.Alert(Main.GetErrorMessage(pathResponse));
                return;
            }

            this.LoadPaths();
        }
        else
        {
            const pathResponse = await this.SaveRoot(
                "update_root",
                {
                    old_web_path: this.elements.tabs.paths.activePath!,
                    new_web_path: webPath,
                    new_local_path: localPath
                }
            );
            if (typeof pathResponse === "string")
            {
                Main.Alert(Main.GetErrorMessage(pathResponse));
                return;
            }

            this.LoadPaths();
        }
    }

    private async SaveRoot(
        method: "add_root" | "update_root" | "delete_root",
        data:
        {
            web_path?: string,
            local_path?: string,
            old_web_path?: string,
            new_web_path?: string,
            new_local_path?: string
        } = {}
    ): Promise<string | true>
    {
        const pathResponse = await Main.XHR<object>(
        {
            url: `${Main.WEB_ROOT}/api/v1/directory/`,
            method: "POST",
            data:
            {
                id: Main.RetreiveCache("uid"),
                token: Main.RetreiveCache("token"),
                method: method,
                ...data
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

        return (pathResponse.response as IServerErrorResponse).error !== undefined ?
            (pathResponse.response as IServerErrorResponse).error :
            true;
    }
}
new Admin();

interface IRootsResponse
{
    paths:
    {
        web_path: string;
        local_path: string;
    }[];
}