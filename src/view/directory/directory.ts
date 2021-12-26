import { Main, IXHRResolve, IXHRReject, IServerErrorResponse } from "../../assets/js/main.js";

class Directory
{
    private firstLoad: boolean;
    private elements:
    {
        directoryListing: HTMLTableSectionElement;
    };
    private directory: string[];

    constructor()
    {
        new Main();

        this.firstLoad = true;
        this.elements =
        {
            directoryListing: Main.GetElement("#directoryListing")    
        };
        this.directory = [];

        //Navigation bar highlight override.
        Main.GetElement<HTMLAnchorElement>(`.navigationContainer a[href='${Main.WEB_ROOT}/view/directory/']`).classList.add("accent");

        //Listen for page navigation.
        window.addEventListener("popstate", (e) => { this.OnWindowPopState(window.location.pathname); });
        this.OnWindowPopState(window.location.pathname);
    }

    private OnWindowPopState(path: string)
    {
        const urlPartsToRemove = Main.WEB_ROOT.split("/").filter(n => n).length + 2; // +2 for .../view/directory
        const urlParts = path.split("/").filter(n => n).slice(urlPartsToRemove);
        this.LoadDirectory(urlParts.join("/"), true);
    }

    private async LoadDirectory(path: string, fromPopState: boolean)
    {
        const directoryResponse = await this.GetDirectory(path);
        if (typeof directoryResponse === "string")
        {
            Main.Alert(Main.GetErrorMessage(directoryResponse));
            return;
        }

        //Update directory and URL.
        this.directory = directoryResponse.path;
        document.title = `${directoryResponse.path[directoryResponse.path.length - 1]??"Directory"} | ${document.title.split("|").at(-1)}`;
        //Dont push to history if it is the first load or if we are coming from popstate.
        if (!this.firstLoad && !fromPopState)
        {
            window.history.pushState(null, "", Main.WEB_ROOT + "/view/directory/" + this.directory.join("/"));
        }
        this.firstLoad = false;
        
        //Clear the directory listing.
        this.elements.directoryListing.innerHTML = "";

        if (directoryResponse.path.length > 0)
        {
            //Add the parent directory link.
            const row = this.CreateDirectoryItem(false, "..", true);
            row.addEventListener("click", () => { this.LoadDirectory(directoryResponse.path.slice(0, -1).join("/"), false); });
            this.elements.directoryListing.appendChild(row);
        }

        //Add the directories first.
        for (const directory of directoryResponse.directories)
        {
            const row = this.CreateDirectoryItem(!directoryResponse.sharedPath, directory, true);
            this.elements.directoryListing.appendChild(row);
        }

        //Add the files.
        for (const file of directoryResponse.files)
        {
            const row = this.CreateDirectoryItem(!directoryResponse.sharedPath, `${file.name}${file.extension ? `.${file.extension}` : ''}`, false, file);
            this.elements.directoryListing.appendChild(row);
        }
    }

    //Thanks copilot for reading my html structure to generate most of this code :) (nice time save).
    private CreateDirectoryItem(optionsEnabled: boolean, displayName: string, isDirectory: boolean, file?: IFile): HTMLTableRowElement
    {
        const row = document.createElement("tr");
        row.classList.add("listItem");

        //#region Name column
        const nameColumn = document.createElement("td");
        nameColumn.classList.add("nameColumn");
        
        const table = document.createElement("table");
        const tbody = document.createElement("tbody");
        const tr = document.createElement("tr");

        const td1 = document.createElement("td");
        const td2 = document.createElement("td");

        const img = document.createElement("img");
        img.src = isDirectory ? `${Main.WEB_ROOT}/assets/images/folder.png` : `${Main.WEB_ROOT}/assets/images/file.png`;
        img.alt = isDirectory ? "Folder" : "File";
        img.classList.add("icon");

        const p = document.createElement("p");
        p.innerText = displayName;

        td1.appendChild(img);
        td2.appendChild(p);

        tr.appendChild(td1);
        tr.appendChild(td2);
        tbody.appendChild(tr);
        table.appendChild(tbody);
        nameColumn.appendChild(table);

        row.appendChild(nameColumn);
        //#endregion

        //#region Type column
        const typeColumn = document.createElement("td");
        typeColumn.classList.add("typeColumn");

        const p2 = document.createElement("p");
        p2.innerText = isDirectory ? "Folder" : file!.extension || "File";

        typeColumn.appendChild(p2);

        row.appendChild(typeColumn);
        //#endregion

        //#region Date column
        const dateColumn = document.createElement("td");
        dateColumn.classList.add("dateColumn");

        const p3 = document.createElement("p");
        p3.innerText = isDirectory ? '' : Directory.FormatUnix(file!.lastModified * 1000);

        dateColumn.appendChild(p3);

        row.appendChild(dateColumn);
        //#endregion

        //#region Size column
        const sizeColumn = document.createElement("td");
        sizeColumn.classList.add("sizeColumn");

        const p4 = document.createElement("p");
        p4.innerText = isDirectory ? "" : Directory.FormatBytes(file!.size);

        sizeColumn.appendChild(p4);
        
        row.appendChild(sizeColumn);
        //#endregion

        //#region Options column
        
        const optionsColumn = document.createElement("td");
        optionsColumn.classList.add("optionsColumn");

        var button: HTMLButtonElement;
        if (optionsEnabled)
        {
            button = document.createElement("button");
            button.innerText = "Sharing";
            // button.onclick = () => { this.ShowSharingDialog(file!.path); };

            optionsColumn.appendChild(button);
        }
        
        row.appendChild(optionsColumn);
        //#endregion

        if (isDirectory)
        {
            row.onclick = (e) =>
            {
                //Make sure the user is not clicking on the share button.
                if (e.target !== button)
                {
                    this.LoadDirectory(this.directory.concat(displayName).join("/"), false);
                }
            }
        }

        return row;
    }

    private async GetDirectory(path: string): Promise<string | IDirectoryResponse>
    {
        const directoryResponse = await Main.XHR<IDirectoryResponse>(
        {
            url: `${Main.WEB_ROOT}/api/v1/directory/${path}`,
            method: "GET",
            data:
            {
                uid: Main.RetreiveCache("uid"),
                token: Main.RetreiveCache("token")
            }
        })
        .catch((error: IXHRReject<IServerErrorResponse>) =>
        {
            return error;
        });

        return (directoryResponse.response as IServerErrorResponse).error !== undefined ?
            (directoryResponse.response as IServerErrorResponse).error :
            (directoryResponse.response as IDirectoryResponse);
    }

    private static FormatUnix(unixTime: number): string
    {
        //String format: DD/MM/YYYY HH:mm
        const date = new Date(unixTime);
        return `${date.getDate() < 10 ? "0" + date.getDate().toString() : date.getDate()}/${
            (date.getMonth() + 1) < 10 ? "0" + date.getMonth().toString() : date.getMonth()}/${
            date.getFullYear()} ${
            date.getHours() < 10 ? "0" + date.getHours().toString() : date.getHours()}:${
            date.getMinutes() < 10 ? "0" + date.getMinutes().toString() : date.getMinutes()
        }`;
    }

    public static FormatBytes(bytes: number, decimals = 2): string
    {
        if (bytes === 0) { return '0 Bytes'; }

        var k = 1024;
        var dm = decimals < 0 ? 0 : decimals;
        var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        var i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
}
new Directory();

interface IDirectoryResponse
{
    sharedPath: boolean;
    path: string[];
    directories: string[];
    files: IFile[];
}

interface IFile
{
    name: string;
    extension?: string;
    size: number;
    lastModified: number;
    mimeType: string;
}