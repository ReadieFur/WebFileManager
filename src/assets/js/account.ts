import { Main, Dictionary, IXHRResolve, IXHRReject, IServerErrorResponse, EErrorMessages } from "./main.js";

export class Account
{
    private static GAPI: {
        loaded: boolean;
        loading: boolean;
        clientID: string | null;
    } = {
        loaded: false,
        loading: false,
        clientID: null
    };

    private static Post<T>(data: Dictionary<any>): Promise<IXHRResolve<T> | IXHRReject<IServerErrorResponse>>
    {
        return Main.XHR<T>(
        {
            url: Main.WEB_ROOT + "/api/v1/account/",
            method: "POST",
            data: data,
            headers:
            {
                "Content-Type": "application/x-www-form-urlencoded"
            }
        })
        .catch((error: IXHRReject<IServerErrorResponse>) =>
        {
            return error;
        });
    }

    public static GetResult<T>(xhrResponse: IXHRResolve<any> | IXHRReject<IServerErrorResponse>): IResult<T>
    {
        if ((xhrResponse as IXHRReject<IServerErrorResponse>).error !== undefined)
        {
            return {
                error: (xhrResponse as IXHRReject<IServerErrorResponse>).response.error,
                data: undefined
            };
        }

        return {
            error: false,
            data: (xhrResponse as IXHRResolve<T>).response
        };
    }

    public static IsRootAdminAccount(uid: string): boolean
    {
        //We know the ID of the admin account because it is hardcoded in the database setup, I will likley change this in the future though.
        return uid === "61c51ce0ab3d0191283069";
    }

    public static async CreateAcount(
        id: string,
        token: string,
        username: string,
        password: string,
        admin: 0 | 1
    ): Promise<IResult<ICreateAccountResponse>>
    {
        return this.GetResult(await Account.Post(
        {
            method: "create_account",
            id: id,
            token: token,
            username: username,
            password: password,
            admin: admin
        }));
    }

    public static async UpdateAccount(
        id: string,
        token: string,
        uid: string,
        old_password: string,
        new_password: string,
        admin: 0 | 1 | ""
    ): Promise<IResult<object>>
    {
        return this.GetResult(await Account.Post(
        {
            method: "update_account",
            id: id,
            token: token,
            uid: uid,
            old_password: old_password,
            new_password: new_password,
            admin: admin
        }));
    }

    public static async DeleteAccount(
        id: string,
        token: string,
        uid: string
    ): Promise<IResult<object>>
    {
        return this.GetResult(await Account.Post(
        {
            method: "delete_account",
            id: id,
            token: token,
            uid: uid
        }));
    }

    public static async LogIn(
        username: string,
        password: string
    ): Promise<IResult<ILogInResponse>>
    {
        return this.GetResult(await Account.Post(
        {
            method: "log_in",
            username: username,
            password: password
        }));
    }

    public static async RevokeSession(
        id: string,
        token: string,
        uid: string
    ): Promise<IResult<object>>
    {
        return this.GetResult(await Account.Post(
        {
            method: "revoke_session",
            id: id,
            token: token,
            uid: uid
        }));
    }

    public static async VerifyToken(
        id: string,
        token: string
    ): Promise<IResult<object>>
    {
        return this.GetResult(await Account.Post(
        {
            method: "verify_token",
            id: id,
            token: token
        }));
    }

    public static async GetAccountDetails(
        id: string,
        token: string,
        uid: string
    ): Promise<IResult<IAccountDetailsResponse>>
    {
        return this.GetResult(await Account.Post(
        {
            method: "get_account_details",
            id: id,
            token: token,
            uid: uid
        }));
    }

    public static async GetAllAccounts(
        id: string,
        token: string
    ): Promise<IResult<IAccountsResponse>>
    {
        return this.GetResult(await Account.Post(
        {
            method: "get_all_accounts",
            id: id,
            token: token
        }));
    }
}

export interface IResult<T>
{
    error: string | false;
    data?: T;
} 

export interface ICreateAccountResponse
{
    uid: string;
}

export interface ILogInResponse
{
    uid: string;
    token: string;
}

export interface IAccountDetailsResponse
{
    uid: string;
    username: string;
    admin: 0 | 1;
}

export interface IAccountsResponse
{
    accounts: IAccountDetailsResponse[];
}