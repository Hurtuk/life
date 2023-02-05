import { Tag } from "./tag";

export class Chapter {
    title: string;
    content: string;
    startDate: Date;
    endDate: Date;
    narrated: string;
    people: string[];
    tags: Tag[];
}