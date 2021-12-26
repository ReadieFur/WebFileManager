import { Main, Dictionary, IXHRResolve, IXHRReject } from "./main.js";

export class Account
{
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

    private static GetResult<T>(xhrResponse: IXHRResolve<any> | IXHRReject<IServerErrorResponse>): IResult<T>
    {
        if ((xhrResponse as IXHRReject<IServerErrorResponse>).error !== undefined)
        {
            return {
                error: (xhrResponse as IXHRReject<IServerErrorResponse>).error,
                data: undefined
            };
        }

        return {
            error: false,
            data: (xhrResponse as IXHRResolve<T>).response
        };
    }

    public static async CreateAcount(
        username: string,
        password: string
    ): Promise<IResult<ICreateAccountResponse>>
    {
        return this.GetResult(await Account.Post(
        {
            method: "create_account",
            username: username,
            password: password
        }));
    }

    public static async UpdateAccount(
        id: string,
        token: string,
        uid: string,
        old_password: string,
        new_password: string,
        admin: 0 | 1 | null
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

export interface IServerErrorResponse
{
    error: string;
}