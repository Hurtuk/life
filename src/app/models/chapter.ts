import { Person } from "./person";
import { Tag } from "./tag";

export class Chapter {
    id: number;
    title: string;
    content: string;
    startDate: Date;
    endDate: Date;
    narrated: boolean;
    people: Person[];
    tags: Tag[];
    idAssociation: number;
}