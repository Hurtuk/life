import { Person } from "./person";
import { Tag } from "./tag";

export class Displayable {
    title: string;
    startDate: Date;
    endDate: Date;
    tags: Tag[];
    content: string;
    photo: string;
    photoYear: string;
    color: string;
    people: Person[];
}